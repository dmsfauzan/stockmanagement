<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Livewire\Dashboard\DashboardIndex;
use App\Livewire\Layout\WarehouseSwitcher;
use App\Livewire\Reports\IncomingReport;
use App\Livewire\Reports\WarehouseComparisonReport;
use App\Models\Category;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Item;
use App\Models\Location;
use App\Models\Rack;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Services\Inventory\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class MultiWarehouseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouseA;

    private Warehouse $warehouseB;

    private Location $locationA;

    private Location $locationB;

    private Item $item;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRoleId = DB::table('roles')->insertGetId(['name' => 'Administrator', 'slug' => 'admin', 'is_system' => true, 'created_at' => now(), 'updated_at' => now()]);
        $permId = DB::table('permissions')->insertGetId(['slug' => 'admin', 'name' => 'All', 'group' => 'system', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('role_permission')->insert(['role_id' => $adminRoleId, 'permission_id' => $permId]);

        $this->admin = User::factory()->create();
        DB::table('user_role')->insert(['user_id' => $this->admin->id, 'role_id' => $adminRoleId]);
        $this->actingAs($this->admin);

        $this->unit = Unit::create(['code' => 'PCS', 'name' => 'Pieces']);
        $cat = Category::create(['code' => 'ELEC', 'name' => 'Electronics', 'status' => 'active']);

        $this->warehouseA = Warehouse::create(['code' => 'WH-A', 'name' => 'Warehouse A', 'status' => 'active']);
        $this->warehouseB = Warehouse::create(['code' => 'WH-B', 'name' => 'Warehouse B', 'status' => 'active']);

        $zoneA = Zone::create(['warehouse_id' => $this->warehouseA->id, 'code' => 'Z-A', 'name' => 'Zone A']);
        $rackA = Rack::create(['zone_id' => $zoneA->id, 'code' => 'A01', 'name' => 'Rack A01']);
        $this->locationA = Location::create(['rack_id' => $rackA->id, 'code' => 'A01-01', 'name' => 'Bin 01']);

        $zoneB = Zone::create(['warehouse_id' => $this->warehouseB->id, 'code' => 'Z-B', 'name' => 'Zone B']);
        $rackB = Rack::create(['zone_id' => $zoneB->id, 'code' => 'B01', 'name' => 'Rack B01']);
        $this->locationB = Location::create(['rack_id' => $rackB->id, 'code' => 'B01-01', 'name' => 'Bin 01']);

        $this->item = Item::create([
            'sku' => 'BRG-MW-1', 'name' => 'Multi Warehouse Item', 'category_id' => $cat->id,
            'unit_id' => $this->unit->id, 'minimum_stock' => 10, 'maximum_stock' => 500, 'status' => 'active',
        ]);
    }

    public function test_switcher_sets_session_and_dashboard_filters_to_selected_warehouse(): void
    {
        LedgerService::record($this->item->id, $this->warehouseA->id, $this->locationA->id, TransactionType::Opening, 'opening', 0, 100, 0);
        LedgerService::record($this->item->id, $this->warehouseB->id, $this->locationB->id, TransactionType::Opening, 'opening', 0, 20, 0);

        $switcher = Livewire::test(WarehouseSwitcher::class);
        $switcher->call('select', $this->warehouseA->id);
        $switcher->assertSet('activeWarehouseId', $this->warehouseA->id);
        $this->assertEquals($this->warehouseA->id, session('active_warehouse_id'));

        $dashboard = Livewire::withQueryParams([])->test(DashboardIndex::class);
        $dashboard->assertSet('warehouseFilter', $this->warehouseA->id);
        $dashboard->assertViewHas('totalStock', 100);
        $dashboard->assertViewHas('activeWarehouseName', 'Warehouse A');

        $switcher->call('select', null);
        $switcher->assertSet('activeWarehouseId', null);
        $this->assertNull(session('active_warehouse_id'));

        $global = Livewire::test(DashboardIndex::class);
        $global->assertViewHas('totalStock', 120);
    }

    public function test_incoming_report_filtered_by_warehouse_returns_correct_rows(): void
    {
        $supplier = Supplier::create(['code' => 'SUP-MW', 'name' => 'Supplier MW', 'status' => 'active']);

        $receiptA = GoodsReceipt::create([
            'number' => 'GR-MW-A1', 'transaction_date' => today(), 'supplier_id' => $supplier->id,
            'warehouse_id' => $this->warehouseA->id, 'status' => 'posted',
            'created_by' => $this->admin->id, 'posted_by' => $this->admin->id, 'posted_at' => now(),
        ]);
        GoodsReceiptItem::create([
            'goods_receipt_id' => $receiptA->id, 'item_id' => $this->item->id,
            'quantity' => 40, 'unit_id' => $this->unit->id, 'location_id' => $this->locationA->id,
        ]);

        $receiptB = GoodsReceipt::create([
            'number' => 'GR-MW-B1', 'transaction_date' => today(), 'supplier_id' => $supplier->id,
            'warehouse_id' => $this->warehouseB->id, 'status' => 'posted',
            'created_by' => $this->admin->id, 'posted_by' => $this->admin->id, 'posted_at' => now(),
        ]);
        GoodsReceiptItem::create([
            'goods_receipt_id' => $receiptB->id, 'item_id' => $this->item->id,
            'quantity' => 7, 'unit_id' => $this->unit->id, 'location_id' => $this->locationB->id,
        ]);

        $component = Livewire::test(IncomingReport::class, ['warehouseFilter' => (string) $this->warehouseB->id]);

        $component->assertViewHas('totals', fn ($totals) => (int) $totals['qty'] === 7 && (int) $totals['rows'] === 1);
        $component->assertSee('GR-MW-B1');
        $component->assertDontSee('GR-MW-A1');
    }

    public function test_comparison_report_aggregates_per_warehouse(): void
    {
        LedgerService::record($this->item->id, $this->warehouseA->id, $this->locationA->id, TransactionType::Opening, 'opening', 0, 100, 0);
        LedgerService::record($this->item->id, $this->warehouseB->id, $this->locationB->id, TransactionType::Opening, 'opening', 0, 5, 0);

        $component = Livewire::test(WarehouseComparisonReport::class);

        $component->assertViewHas('totals', fn ($totals) => (int) $totals['on_hand'] === 105 && (int) $totals['low'] === 1);
        $component->assertSee('Warehouse A');
        $component->assertSee('Warehouse B');
    }
}
