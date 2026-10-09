<?php

namespace Tests\Feature;

use App\Livewire\MasterData\ItemForm;
use App\Livewire\MasterData\ItemShow;
use App\Models\Category;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\Location;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\UnitConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MultiUnitConversionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::where('email', 'admin@stock.test')->firstOrFail());
    }

    private function makeItem(): Item
    {
        return Item::create([
            'sku' => 'BRG-UOM-'.uniqid(),
            'name' => 'Multi UOM Item',
            'category_id' => Category::firstOrFail()->id,
            'unit_id' => Unit::where('code', 'PCS')->firstOrFail()->id,
            'minimum_stock' => 0,
            'maximum_stock' => 0,
            'status' => 'active',
        ]);
    }

    public function test_factor_and_resolve(): void
    {
        $item = $this->makeItem();
        $pcs = Unit::where('code', 'PCS')->firstOrFail();
        $box = Unit::where('code', 'BOX')->firstOrFail();

        UnitConversionService::saveConversion($item->id, $box->id, 12);

        $this->assertSame(1.0, UnitConversionService::factor($item->id, $pcs->id));
        $this->assertSame(12.0, UnitConversionService::factor($item->id, $box->id));

        $resolved = UnitConversionService::resolve($item->id, $box->id, 3);
        $this->assertSame(12.0, $resolved['factor']);
        $this->assertSame(36, $resolved['base_quantity']);
    }

    public function test_goods_receipt_posts_base_quantity(): void
    {
        $item = $this->makeItem();
        $box = Unit::where('code', 'BOX')->firstOrFail();

        UnitConversionService::saveConversion($item->id, $box->id, 12);

        $warehouse = Warehouse::where('code', 'WH-JKT')->firstOrFail();
        $location = Location::where('code', 'A01-01')->firstOrFail();

        $receipt = GoodsReceipt::create([
            'number' => 'GR-UOM-1', 'transaction_date' => today(),
            'supplier_id' => Supplier::firstOrFail()->id,
            'warehouse_id' => $warehouse->id, 'status' => 'approved',
            'created_by' => auth()->id(), 'approved_by' => auth()->id(), 'approved_at' => now(),
        ]);

        $receipt->receiptItems()->create([
            'item_id' => $item->id, 'quantity' => 2, 'unit_cost' => 1000,
            'unit_id' => $box->id, 'location_id' => $location->id,
        ]);

        $line = $receipt->receiptItems()->first();
        $this->assertSame(12, (int) $line->conversion_factor);
        $this->assertSame(24, (int) $line->base_quantity);

        InventoryService::postGoodsReceipt($receipt->fresh('receiptItems'));

        $movement = StockMovement::where('item_id', $item->id)->latest('id')->first();
        $this->assertSame(24, (int) $movement->quantity_in);
    }

    public function test_item_form_persists_conversion(): void
    {
        $box = Unit::where('code', 'BOX')->firstOrFail();
        $pcs = Unit::where('code', 'PCS')->firstOrFail();
        $category = Category::firstOrFail();

        Livewire::test(ItemForm::class)
            ->set('sku', 'BRG-UOM-FORM')
            ->set('name', 'Form UOM Item')
            ->set('category_id', (string) $category->id)
            ->set('unit_id', (string) $pcs->id)
            ->set('conversionUnitId', (string) $box->id)
            ->set('conversionFactor', '12')
            ->call('addConversion')
            ->assertSet('conversionRows.0.factor', '12')
            ->call('save');

        $item = Item::where('sku', 'BRG-UOM-FORM')->firstOrFail();
        $this->assertDatabaseHas('item_unit_conversions', ['item_id' => $item->id, 'unit_id' => $box->id]);
    }

    public function test_allowed_unit_ids_includes_base_and_conversions(): void
    {
        $item = $this->makeItem();
        $pcs = Unit::where('code', 'PCS')->firstOrFail();
        $box = Unit::where('code', 'BOX')->firstOrFail();

        UnitConversionService::saveConversion($item->id, $box->id, 12);

        $allowed = UnitConversionService::allowedUnitIds($item->id);

        $this->assertContains($pcs->id, $allowed);
        $this->assertContains($box->id, $allowed);
        $this->assertCount(2, $allowed);
    }

    public function test_item_show_renders_conversions(): void
    {
        $item = $this->makeItem();
        $box = Unit::where('code', 'BOX')->firstOrFail();

        UnitConversionService::saveConversion($item->id, $box->id, 12);

        Livewire::test(ItemShow::class, ['item' => $item->id])
            ->assertSee('Konversi Satuan')
            ->assertSee('BOX');
    }

    public function test_api_item_show_includes_conversions(): void
    {
        $item = $this->makeItem();
        $box = Unit::where('code', 'BOX')->firstOrFail();

        UnitConversionService::saveConversion($item->id, $box->id, 12);

        $this->getJson("/api/items/{$item->id}?include=conversions")
            ->assertOk()
            ->assertJsonPath('data.conversions.0.unit_code', 'BOX')
            ->assertJsonPath('data.conversions.0.factor', 12);
    }
}
