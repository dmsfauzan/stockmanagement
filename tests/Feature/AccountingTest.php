<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\GoodsIssue;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\Location;
use App\Models\Rack;
use App\Models\Setting;
use App\Models\StockAdjustment;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Services\Accounting\JournalService;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouse;

    private Warehouse $otherWarehouse;

    private Location $location;

    private Item $item;

    private Unit $unit;

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
        $cat = Category::create(['code' => 'ELEC', 'name' => 'Electronics', 'status' => 'active']);
        $this->warehouse = Warehouse::create(['code' => 'WH-TST', 'name' => 'Test Warehouse', 'status' => 'active']);
        $this->otherWarehouse = Warehouse::create(['code' => 'WH-OTH', 'name' => 'Other Warehouse', 'status' => 'active']);
        $zone = Zone::create(['warehouse_id' => $this->warehouse->id, 'code' => 'Z-A', 'name' => 'Zone A']);
        $rack = Rack::create(['zone_id' => $zone->id, 'code' => 'A01', 'name' => 'Rack A01']);
        $this->location = Location::create(['rack_id' => $rack->id, 'code' => 'A01-01', 'name' => 'Bin 01']);
        $this->item = Item::create([
            'sku' => 'BRG-ACC-1', 'name' => 'Accounting Item', 'category_id' => $cat->id,
            'unit_id' => $this->unit->id, 'minimum_stock' => 0, 'maximum_stock' => 1000, 'status' => 'active', 'cost' => 100,
        ]);

        Setting::set('account.inventory', '1300', 'accounting');
        Setting::set('account.cogs', '5100', 'accounting');
        Setting::set('account.adjustment_gain', '4210', 'accounting');
        Setting::set('account.adjustment_loss', '5210', 'accounting');
        Setting::set('account.transfer_clearing', '1310', 'accounting');
        Setting::set('account.goods_receipt_clearing', '2000', 'accounting');
    }

    private function makePostedReceipt(float $unitCost = 100, int $qty = 10): GoodsReceipt
    {
        $supplier = Supplier::create(['code' => 'SUP-ACC', 'name' => 'Supplier Acc', 'status' => 'active']);
        $receipt = GoodsReceipt::create([
            'number' => 'GR-ACC-'.time().'-'.mt_rand(1000, 9999),
            'transaction_date' => today(),
            'supplier_id' => $supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'approved',
            'created_by' => $this->admin->id,
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);
        $receipt->receiptItems()->create([
            'item_id' => $this->item->id,
            'quantity' => $qty,
            'unit_cost' => $unitCost,
            'unit_id' => $this->unit->id,
            'location_id' => $this->location->id,
        ]);
        InventoryService::postGoodsReceipt($receipt->fresh('receiptItems'));

        return $receipt->fresh();
    }

    public function test_incoming_journal_debits_inventory(): void
    {
        $from = Carbon::now()->subDay();

        $this->makePostedReceipt(120, 10);

        $rows = JournalService::journalRows($from, Carbon::now()->addDay());
        $row = $rows->firstWhere('type', TransactionType::Incoming->value);

        $this->assertNotNull($row);
        $this->assertEquals('1300', $row['debit_account']);
        $this->assertEquals('2000', $row['credit_account']);
        $this->assertEqualsWithDelta(1200.0, $row['total_cost'], 0.01);

        $totals = JournalService::totals($rows);
        $this->assertEquals($totals['debits'], $totals['credits']);
    }

    public function test_outgoing_journal_debits_cogs(): void
    {
        $from = Carbon::now()->subDay();

        $receipt = $this->makePostedReceipt(100, 20);

        $issue = GoodsIssue::create([
            'number' => 'GI-ACC-'.time().'-'.mt_rand(1000, 9999),
            'transaction_date' => today(),
            'warehouse_id' => $this->warehouse->id,
            'destination' => 'Test destination',
            'status' => 'approved',
            'created_by' => $this->admin->id,
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);
        $issue->issueItems()->create([
            'item_id' => $this->item->id,
            'quantity' => 5,
            'unit_id' => $this->unit->id,
            'location_id' => $this->location->id,
        ]);
        InventoryService::postGoodsIssue($issue->fresh('issueItems'));

        $rows = JournalService::journalRows($from, Carbon::now()->addDay());
        $outgoing = $rows->firstWhere('type', TransactionType::Outgoing->value);

        $this->assertNotNull($outgoing);
        $this->assertEquals('5100', $outgoing['debit_account']);
        $this->assertEquals('1300', $outgoing['credit_account']);
        $this->assertTrue($receipt->fresh()->isPosted());
    }

    public function test_adjustment_out_journal_debits_loss(): void
    {
        $from = Carbon::now()->subDay();

        $this->makePostedReceipt(50, 10);

        $adj = StockAdjustment::create([
            'number' => 'ADJ-ACC-'.time().'-'.mt_rand(1000, 9999),
            'transaction_date' => today(),
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->location->id,
            'reason' => 'Stock Count Error',
            'status' => 'approved',
            'created_by' => $this->admin->id,
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);
        $adj->items()->create([
            'item_id' => $this->item->id,
            'system_quantity' => 10,
            'actual_quantity' => 7,
            'difference' => -3,
        ]);
        InventoryService::postStockAdjustment($adj->fresh('items'));

        $rows = JournalService::journalRows($from, Carbon::now()->addDay());
        $row = $rows->firstWhere('type', TransactionType::AdjustmentOut->value);

        $this->assertNotNull($row);
        $this->assertEquals('5210', $row['debit_account']);
        $this->assertEquals('1300', $row['credit_account']);
    }

    public function test_journal_warehouse_filter(): void
    {
        $from = Carbon::now()->subDay();

        $this->makePostedReceipt(80, 10);

        $all = JournalService::journalRows($from, Carbon::now()->addDay());
        $this->assertNotEmpty($all);

        $filtered = JournalService::journalRows($from, Carbon::now()->addDay(), $this->otherWarehouse->id);
        $this->assertTrue($filtered->isEmpty());

        $sameWh = JournalService::journalRows($from, Carbon::now()->addDay(), $this->warehouse->id);
        $this->assertEquals($all->count(), $sameWh->count());
    }
}
