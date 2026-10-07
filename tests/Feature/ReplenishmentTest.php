<?php

namespace Tests\Feature;

use App\Livewire\Reports\ReplenishmentReport;
use App\Models\Category;
use App\Models\Item;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\Rack;
use App\Models\StockBalance;
use App\Models\Supplier;
use App\Models\SupplierItemPrice;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Services\Inventory\ReplenishmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class ReplenishmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouse;

    private Location $location;

    private Supplier $supplier;

    private Item $lowItem;

    private Item $healthyItem;

    private Unit $unit;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $roleId = DB::table('roles')->insertGetId(['name' => 'Administrator', 'slug' => 'admin', 'is_system' => true, 'created_at' => now(), 'updated_at' => now()]);
        $permId = DB::table('permissions')->insertGetId(['slug' => 'admin', 'name' => 'All', 'group' => 'system', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('role_permission')->insert(['role_id' => $roleId, 'permission_id' => $permId]);

        $this->admin = User::factory()->create();
        DB::table('user_role')->insert(['user_id' => $this->admin->id, 'role_id' => $roleId]);
        $this->actingAs($this->admin);

        $this->unit = Unit::create(['code' => 'PCS', 'name' => 'Pieces']);
        $this->category = Category::create(['code' => 'ELEC', 'name' => 'Electronics', 'status' => 'active']);
        $this->supplier = Supplier::create(['code' => 'SUP001', 'name' => 'PT Sumber', 'status' => 'active']);
        $this->warehouse = Warehouse::create(['code' => 'WH-TST', 'name' => 'Test Warehouse', 'status' => 'active']);
        $zone = Zone::create(['warehouse_id' => $this->warehouse->id, 'code' => 'Z-A', 'name' => 'Zone A']);
        $rack = Rack::create(['zone_id' => $zone->id, 'code' => 'A01', 'name' => 'Rack A01']);
        $this->location = Location::create(['rack_id' => $rack->id, 'code' => 'A01-01', 'name' => 'Bin 01']);

        $this->lowItem = Item::create([
            'sku' => 'BRG-LOW-1',
            'name' => 'Low Item',
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'minimum_stock' => 20,
            'maximum_stock' => 100,
            'cost' => 5000,
            'primary_supplier_id' => $this->supplier->id,
            'status' => 'active',
        ]);

        $this->healthyItem = Item::create([
            'sku' => 'BRG-OK-1',
            'name' => 'Healthy Item',
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'minimum_stock' => 5,
            'maximum_stock' => 100,
            'cost' => 10000,
            'status' => 'active',
        ]);

        SupplierItemPrice::create([
            'supplier_id' => $this->supplier->id,
            'item_id' => $this->lowItem->id,
            'price' => 4500,
        ]);

        StockBalance::create([
            'item_id' => $this->lowItem->id,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->location->id,
            'quantity_on_hand' => 3,
            'quantity_reserved' => 0,
        ]);

        StockBalance::create([
            'item_id' => $this->healthyItem->id,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->location->id,
            'quantity_on_hand' => 80,
            'quantity_reserved' => 0,
        ]);
    }

    public function test_suggestions_returns_low_item_with_suggested_qty(): void
    {
        $rows = ReplenishmentService::suggestions();

        $this->assertCount(1, $rows);
        $low = $rows->first();
        $this->assertEquals($this->lowItem->id, $low['item_id']);
        $this->assertEquals(97, $low['suggestedQty']);
        $this->assertEquals(4500, (float) $low['best_price']);
    }

    public function test_suggestions_filters_by_warehouse_and_search_and_category(): void
    {
        $otherWh = Warehouse::create(['code' => 'WH-OTH', 'name' => 'Other', 'status' => 'active']);
        $zone2 = Zone::create(['warehouse_id' => $otherWh->id, 'code' => 'Z-B', 'name' => 'Zone B']);
        $rack2 = Rack::create(['zone_id' => $zone2->id, 'code' => 'B01', 'name' => 'Rack B01']);
        $loc2 = Location::create(['rack_id' => $rack2->id, 'code' => 'B01-01', 'name' => 'Bin 01']);

        StockBalance::create([
            'item_id' => $this->lowItem->id,
            'warehouse_id' => $otherWh->id,
            'location_id' => $loc2->id,
            'quantity_on_hand' => 1,
            'quantity_reserved' => 0,
        ]);

        $all = ReplenishmentService::suggestions();
        $this->assertCount(2, $all);

        $filtered = ReplenishmentService::suggestions($this->warehouse->id);
        $this->assertCount(1, $filtered);

        $searched = ReplenishmentService::suggestions(null, 'LOW');
        $this->assertTrue($searched->every(fn ($r) => str_contains($r['sku'], 'LOW')));

        $noMatch = ReplenishmentService::suggestions(null, 'ZZZZ');
        $this->assertCount(0, $noMatch);

        $byCat = ReplenishmentService::suggestions(null, null, $this->category->id);
        $this->assertCount(2, $byCat);

        $badCat = ReplenishmentService::suggestions(null, null, 99999);
        $this->assertCount(0, $badCat);
    }

    public function test_suggested_qty_formulas(): void
    {
        $itemMax = Item::create([
            'sku' => 'BRG-SUG-1',
            'name' => 'Max Item',
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'minimum_stock' => 10,
            'maximum_stock' => 50,
            'status' => 'active',
        ]);
        StockBalance::create([
            'item_id' => $itemMax->id,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->location->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
        ]);

        $itemMinOnly = Item::create([
            'sku' => 'BRG-SUG-2',
            'name' => 'Min Only',
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'minimum_stock' => 10,
            'maximum_stock' => 0,
            'status' => 'active',
        ]);
        StockBalance::create([
            'item_id' => $itemMinOnly->id,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->location->id,
            'quantity_on_hand' => 2,
            'quantity_reserved' => 0,
        ]);

        $itemZero = Item::create([
            'sku' => 'BRG-SUG-3',
            'name' => 'Zero Config',
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'minimum_stock' => 0,
            'maximum_stock' => 0,
            'status' => 'active',
        ]);
        StockBalance::create([
            'item_id' => $itemZero->id,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->location->id,
            'quantity_on_hand' => 0,
            'quantity_reserved' => 0,
        ]);

        $rows = ReplenishmentService::suggestions()->keyBy('item_id');

        $this->assertEquals(45, $rows[$itemMax->id]['suggestedQty']);
        $this->assertEquals(18, $rows[$itemMinOnly->id]['suggestedQty']);
        $this->assertEquals(0, $rows[$itemZero->id]['suggestedQty']);
    }

    public function test_livewire_page_renders(): void
    {
        Livewire::test(ReplenishmentReport::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.reports.replenishment-report');
    }

    public function test_export_csv_returns_headers(): void
    {
        $component = Livewire::test(ReplenishmentReport::class);

        $response = $component->instance()->exportCsv();
        $this->assertInstanceOf(StreamedResponse::class, $response);

        ob_start();
        $response->sendContent();
        $csv = (string) ob_get_clean();

        $this->assertStringContainsString('SKU,Item,Category,Warehouse', $csv);
        $this->assertStringContainsString('Suggested', $csv);
        $this->assertStringContainsString('BRG-LOW-1', $csv);
        $this->assertStringNotContainsString('BRG-OK-1', $csv);
    }

    public function test_create_po_via_livewire_action(): void
    {
        $key = $this->lowItem->id.':'.$this->warehouse->id;

        Livewire::test(ReplenishmentReport::class)
            ->set('selected', [$key])
            ->set('supplierForPo', (string) $this->supplier->id)
            ->call('createPoFromSelection')
            ->assertRedirect(route('purchase-orders.show', PurchaseOrder::latest('id')->first()));

        $po = PurchaseOrder::latest('id')->first();
        $this->assertNotNull($po);
        $this->assertEquals('draft', $po->status);
        $this->assertEquals($this->supplier->id, $po->supplier_id);
        $this->assertEquals($this->warehouse->id, $po->warehouse_id);
        $this->assertCount(1, $po->items);
        $this->assertEquals($this->lowItem->id, $po->items->first()->item_id);
        $this->assertEquals(97, $po->items->first()->quantity);
        $this->assertEquals(4500, (float) $po->items->first()->unit_price);
    }

    public function test_create_po_infers_most_common_supplier(): void
    {
        $cat2 = $this->category;
        $sup2 = Supplier::create(['code' => 'SUP002', 'name' => 'PT Kedua', 'status' => 'active']);
        $item2 = Item::create([
            'sku' => 'BRG-LOW-2',
            'name' => 'Low Item 2',
            'category_id' => $cat2->id,
            'unit_id' => $this->unit->id,
            'minimum_stock' => 10,
            'maximum_stock' => 30,
            'cost' => 2000,
            'primary_supplier_id' => $sup2->id,
            'status' => 'active',
        ]);
        $item3 = Item::create([
            'sku' => 'BRG-LOW-3',
            'name' => 'Low Item 3',
            'category_id' => $cat2->id,
            'unit_id' => $this->unit->id,
            'minimum_stock' => 10,
            'maximum_stock' => 30,
            'cost' => 3000,
            'primary_supplier_id' => $sup2->id,
            'status' => 'active',
        ]);
        StockBalance::create(['item_id' => $item2->id, 'warehouse_id' => $this->warehouse->id, 'location_id' => $this->location->id, 'quantity_on_hand' => 1, 'quantity_reserved' => 0]);
        StockBalance::create(['item_id' => $item3->id, 'warehouse_id' => $this->warehouse->id, 'location_id' => $this->location->id, 'quantity_on_hand' => 1, 'quantity_reserved' => 0]);

        $keys = [$this->lowItem->id.':'.$this->warehouse->id, $item2->id.':'.$this->warehouse->id, $item3->id.':'.$this->warehouse->id];

        Livewire::test(ReplenishmentReport::class)
            ->set('selected', $keys)
            ->set('supplierForPo', '')
            ->call('createPoFromSelection');

        $po = PurchaseOrder::latest('id')->first();
        $this->assertEquals($sup2->id, $po->supplier_id);
    }

    public function test_route_get_replenishment_requires_auth(): void
    {
        $this->get(route('reports.replenishment'))->assertOk();
    }
}
