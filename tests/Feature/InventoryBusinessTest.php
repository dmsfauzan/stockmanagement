<?php

namespace Tests\Feature;

use App\Enums\StockStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Item;
use App\Models\Location;
use App\Models\Rack;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\LedgerService;
use App\Services\Support\DocumentNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryBusinessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Warehouse $warehouse;
    private Location $location;
    private Item $item;
    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('permissions')->insert([
            ['slug' => 'items.view', 'name' => 'Items View', 'group' => 'items', 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'dashboard.view', 'name' => 'Dashboard', 'group' => 'dashboard', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $roleId = DB::table('roles')->insertGetId(['name' => 'Administrator', 'slug' => 'admin', 'is_system' => true, 'created_at' => now(), 'updated_at' => now()]);
        $permId = DB::table('permissions')->where('slug', 'admin')->value('id');
        if (! $permId) {
            $permId = DB::table('permissions')->insertGetId(['slug' => 'admin', 'name' => 'All', 'group' => 'system', 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('role_permission')->insert(['role_id' => $roleId, 'permission_id' => $permId]);

        $this->admin = User::factory()->create();
        DB::table('user_role')->insert(['user_id' => $this->admin->id, 'role_id' => $roleId]);
        $this->actingAs($this->admin);

        $this->unit = Unit::create(['code' => 'PCS', 'name' => 'Pieces']);
        $cat = Category::create(['code' => 'ELEC', 'name' => 'Electronics', 'status' => 'active']);
        $this->warehouse = Warehouse::create(['code' => 'WH-TST', 'name' => 'Test Warehouse', 'status' => 'active']);
        $zone = Zone::create(['warehouse_id' => $this->warehouse->id, 'code' => 'Z-A', 'name' => 'Zone A']);
        $rack = Rack::create(['zone_id' => $zone->id, 'code' => 'A01', 'name' => 'Rack A01']);
        $this->location = Location::create(['rack_id' => $rack->id, 'code' => 'A01-01', 'name' => 'Bin 01']);
        $this->item = Item::create([
            'sku' => 'BRG-INV-1', 'name' => 'Test Item', 'category_id' => $cat->id,
            'unit_id' => $this->unit->id, 'minimum_stock' => 30, 'maximum_stock' => 300, 'status' => 'active',
        ]);
    }

    public function test_stock_calculation_opening_plus_incoming_minus_outgoing(): void
    {
        LedgerService::record($this->item->id, $this->warehouse->id, $this->location->id, TransactionType::Opening, 'opening', 0, 100, 0);
        LedgerService::record($this->item->id, $this->warehouse->id, $this->location->id, TransactionType::Incoming, 'incoming', 1, 50, 0);
        LedgerService::record($this->item->id, $this->warehouse->id, $this->location->id, TransactionType::Outgoing, 'outgoing', 1, 0, 30);

        $balance = StockBalance::first();
        $this->assertEquals(120, $balance->quantity_on_hand);
        $this->assertEquals(3, StockMovement::count());
        $this->assertEquals(120, StockMovement::latest('id')->first()->balance_after);
    }

    public function test_insufficient_stock_is_rejected(): void
    {
        LedgerService::record($this->item->id, $this->warehouse->id, $this->location->id, TransactionType::Opening, 'opening', 0, 10, 0);
        $this->expectException(\RuntimeException::class);
        LedgerService::record($this->item->id, $this->warehouse->id, $this->location->id, TransactionType::Outgoing, 'outgoing', 1, 0, 15);
        $this->assertEquals(10, StockBalance::first()->quantity_on_hand);
    }

    public function test_low_stock_status(): void
    {
        $this->item->update(['minimum_stock' => 30, 'maximum_stock' => 100]);
        LedgerService::record($this->item->id, $this->warehouse->id, $this->location->id, TransactionType::Opening, 'opening', 0, 20, 0);
        $this->assertSame(StockStatus::LowStock, StockStatus::evaluate(20, 30, 100));
        $this->assertSame(StockStatus::Normal, StockStatus::evaluate(50, 30, 100));
    }

    public function test_out_of_stock_status(): void
    {
        $this->assertSame(StockStatus::OutOfStock, StockStatus::evaluate(0, 30, 100));
    }

    public function test_no_negative_stock(): void
    {
        LedgerService::record($this->item->id, $this->warehouse->id, $this->location->id, TransactionType::Opening, 'opening', 0, 5, 0);
        try {
            LedgerService::record($this->item->id, $this->warehouse->id, $this->location->id, TransactionType::Outgoing, 'outgoing', 1, 0, 999);
            $this->fail('Should have thrown');
        } catch (\RuntimeException) {
            $this->assertEquals(5, StockBalance::first()->quantity_on_hand);
        }
    }

    public function test_posted_goods_receipt_increases_stock(): void
    {
        $receipt = GoodsReceipt::create([
            'number' => DocumentNumberService::generate('GR'), 'transaction_date' => today(),
            'supplier_id' => \App\Models\Supplier::create(['code' => 'SUP-T1', 'name' => 'Supplier Test', 'status' => 'active'])->id,
            'warehouse_id' => $this->warehouse->id, 'status' => 'approved',
            'created_by' => $this->admin->id, 'approved_by' => $this->admin->id, 'approved_at' => now(),
        ]);
        GoodsReceiptItem::create([
            'goods_receipt_id' => $receipt->id, 'item_id' => $this->item->id,
            'quantity' => 25, 'unit_id' => $this->unit->id, 'location_id' => $this->location->id,
        ]);
        InventoryService::postGoodsReceipt($receipt->refresh());
        $this->assertEquals('posted', $receipt->fresh()->status);
        $this->assertEquals(25, StockBalance::first()->quantity_on_hand);
        $receipt->refresh();
        $this->assertNotNull($receipt->posted_at);
    }

    public function test_posted_goods_issue_decreases_stock(): void
    {
        LedgerService::record($this->item->id, $this->warehouse->id, $this->location->id, TransactionType::Opening, 'opening', 0, 100, 0);
        $issue = GoodsIssue::create([
            'number' => DocumentNumberService::generate('GI'), 'transaction_date' => today(),
            'destination' => 'Department X', 'warehouse_id' => $this->warehouse->id, 'status' => 'approved',
            'created_by' => $this->admin->id, 'approved_by' => $this->admin->id, 'approved_at' => now(),
        ]);
        GoodsIssueItem::create([
            'goods_issue_id' => $issue->id, 'item_id' => $this->item->id,
            'quantity' => 20, 'unit_id' => $this->unit->id, 'location_id' => $this->location->id,
        ]);
        InventoryService::postGoodsIssue($issue->refresh());
        $this->assertEquals(80, StockBalance::first()->quantity_on_hand);
        $this->assertEquals('posted', $issue->fresh()->status);
    }

    public function test_document_number_format_is_unique(): void
    {
        $a = DocumentNumberService::generate('GR');
        $b = DocumentNumberService::generate('GR');
        $this->assertNotEquals($a, $b);
        $this->assertMatchesRegularExpression('/^GR-\d{8}-\d{4}$/', $a);
    }

    public function test_transaction_state_machine(): void
    {
        $this->assertTrue(TransactionStatus::Draft->canTransitionTo(TransactionStatus::Submitted));
        $this->assertFalse(TransactionStatus::Posted->canTransitionTo(TransactionStatus::Draft));
        $this->assertTrue(TransactionStatus::Submitted->canTransitionTo(TransactionStatus::Approved));
        $this->assertTrue(TransactionStatus::Submitted->canTransitionTo(TransactionStatus::Rejected));
        $this->assertFalse(TransactionStatus::Rejected->canTransitionTo(TransactionStatus::Approved));
    }
}
