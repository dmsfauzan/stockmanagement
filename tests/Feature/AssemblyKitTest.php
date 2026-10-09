<?php

namespace Tests\Feature;

use App\Livewire\Manufacturing\AssemblyOrderForm;
use App\Livewire\MasterData\ItemForm;
use App\Models\AssemblyOrder;
use App\Models\Category;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\Location;
use App\Models\StockBalance;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\AssemblyService;
use App\Services\Inventory\BomService;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssemblyKitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::where('email', 'admin@stock.test')->firstOrFail());
    }

    private function makeItem(string $sku): Item
    {
        return Item::create([
            'sku' => $sku,
            'name' => $sku,
            'category_id' => Category::firstOrFail()->id,
            'unit_id' => Unit::firstOrFail()->id,
            'minimum_stock' => 0,
            'maximum_stock' => 0,
            'status' => 'active',
        ]);
    }

    private function receive(Item $item, int $qty): void
    {
        $warehouse = Warehouse::where('code', 'WH-JKT')->firstOrFail();
        $location = Location::where('code', 'A01-01')->firstOrFail();

        $receipt = GoodsReceipt::create([
            'number' => 'GR-KIT-'.uniqid(), 'transaction_date' => today(),
            'supplier_id' => Supplier::firstOrFail()->id,
            'warehouse_id' => $warehouse->id, 'status' => 'approved',
            'created_by' => auth()->id(), 'approved_by' => auth()->id(), 'approved_at' => now(),
        ]);

        $receipt->receiptItems()->create([
            'item_id' => $item->id, 'quantity' => $qty, 'unit_cost' => 1000,
            'unit_id' => $item->unit_id, 'location_id' => $location->id,
        ]);

        InventoryService::postGoodsReceipt($receipt->fresh('receiptItems'));
    }

    private function kitContext(): array
    {
        $kit = $this->makeItem('KIT-1');
        $compA = $this->makeItem('COMP-A');
        $compB = $this->makeItem('COMP-B');

        BomService::saveComponent($kit->id, $compA->id, 2);
        BomService::saveComponent($kit->id, $compB->id, 1);

        $this->receive($compA, 10);
        $this->receive($compB, 10);

        return [$kit, $compA, $compB];
    }

    private function onHand(Item $item): int
    {
        return (int) StockBalance::where('item_id', $item->id)->sum('quantity_on_hand');
    }

    public function test_assembly_index_renders(): void
    {
        $this->get(route('assembly-orders.index'))->assertOk()->assertSee('Perakitan / Kit');
    }

    public function test_bom_cost_computation(): void
    {
        [$kit, $compA, $compB] = $this->kitContext();

        $compA->update(['cost' => 5000]);
        $compB->update(['cost' => 2000]);

        $cost = BomService::costForQuantity($kit->id, 1);

        $this->assertSame(12000.0, $cost['total']);
    }

    public function test_assembly_consumes_components_and_produces_kit(): void
    {
        [$kit, $compA, $compB] = $this->kitContext();

        $warehouse = Warehouse::where('code', 'WH-JKT')->firstOrFail();
        $location = Location::where('code', 'A01-01')->firstOrFail();

        $order = AssemblyOrder::create([
            'number' => 'ASM-T-1', 'type' => 'assembly', 'assembly_date' => today(),
            'item_id' => $kit->id, 'quantity' => 2,
            'warehouse_id' => $warehouse->id, 'location_id' => $location->id,
            'status' => 'approved',
            'created_by' => auth()->id(), 'approved_by' => auth()->id(), 'approved_at' => now(),
        ]);

        AssemblyService::post($order);

        $this->assertSame(6, $this->onHand($compA));
        $this->assertSame(8, $this->onHand($compB));
        $this->assertSame(2, $this->onHand($kit));
    }

    public function test_reverse_assembly_restores_stock(): void
    {
        [$kit, $compA, $compB] = $this->kitContext();

        $warehouse = Warehouse::where('code', 'WH-JKT')->firstOrFail();
        $location = Location::where('code', 'A01-01')->firstOrFail();

        $order = AssemblyOrder::create([
            'number' => 'ASM-T-2', 'type' => 'assembly', 'assembly_date' => today(),
            'item_id' => $kit->id, 'quantity' => 1,
            'warehouse_id' => $warehouse->id, 'location_id' => $location->id,
            'status' => 'approved',
            'created_by' => auth()->id(), 'approved_by' => auth()->id(), 'approved_at' => now(),
        ]);

        AssemblyService::post($order);
        AssemblyService::reverse($order->fresh(), 'Batal');

        $this->assertSame(10, $this->onHand($compA));
        $this->assertSame(10, $this->onHand($compB));
        $this->assertSame(0, $this->onHand($kit));
    }

    public function test_disassembly_breaks_kit_into_components(): void
    {
        [$kit, $compA, $compB] = $this->kitContext();

        $warehouse = Warehouse::where('code', 'WH-JKT')->firstOrFail();
        $location = Location::where('code', 'A01-01')->firstOrFail();

        $this->receive($kit, 2);

        $order = AssemblyOrder::create([
            'number' => 'DIS-T-1', 'type' => 'disassembly', 'assembly_date' => today(),
            'item_id' => $kit->id, 'quantity' => 2,
            'warehouse_id' => $warehouse->id, 'location_id' => $location->id,
            'status' => 'approved',
            'created_by' => auth()->id(), 'approved_by' => auth()->id(), 'approved_at' => now(),
        ]);

        AssemblyService::post($order);

        $this->assertSame(0, $this->onHand($kit));
        $this->assertSame(14, $this->onHand($compA));
        $this->assertSame(12, $this->onHand($compB));
    }

    public function test_bom_from_item_form_end_to_end(): void
    {
        $kit = $this->makeItem('KIT-FORM');
        $comp = $this->makeItem('COMP-FORM');

        Livewire::test(ItemForm::class, ['item' => $kit->id])
            ->set('bomComponentId', (string) $comp->id)
            ->set('bomQuantity', '3')
            ->call('addBomComponent')
            ->call('save');

        $this->assertDatabaseHas('item_boms', ['kit_item_id' => $kit->id, 'component_item_id' => $comp->id, 'quantity' => 3]);
    }

    public function test_api_assembly_creates_and_posts(): void
    {
        [$kit] = $this->kitContext();

        $warehouse = Warehouse::where('code', 'WH-JKT')->firstOrFail();
        $location = Location::where('code', 'A01-01')->firstOrFail();

        $this->postJson('/api/assembly-orders', [
            'type' => 'assembly',
            'assembly_date' => today()->toDateString(),
            'item_id' => $kit->id,
            'quantity' => 2,
            'warehouse_id' => $warehouse->id,
            'location_id' => $location->id,
        ])->assertCreated();

        $this->assertSame(2, $this->onHand($kit));

        Livewire::test(AssemblyOrderForm::class)
            ->set('item_id', (string) $kit->id)
            ->set('warehouse_id', (string) $warehouse->id)
            ->set('location_id', (string) $location->id)
            ->assertSee(__('Ketersediaan Komponen'));
    }
}
