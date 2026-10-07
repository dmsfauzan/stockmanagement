<?php

namespace Tests\Feature;

use App\Enums\PurchaseOrderStatus;
use App\Models\Category;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\Rack;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Services\Inventory\InventoryService;
use App\Services\Support\DocumentNumberService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouse;

    private Location $location;

    private Supplier $supplier;

    private Item $item1;

    private Item $item2;

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
        $this->supplier = Supplier::create(['code' => 'SUP001', 'name' => 'PT Sumber Elektronik', 'status' => 'active']);
        $this->warehouse = Warehouse::create(['code' => 'WH-TST', 'name' => 'Test Warehouse', 'status' => 'active']);
        $zone = Zone::create(['warehouse_id' => $this->warehouse->id, 'code' => 'Z-A', 'name' => 'Zone A']);
        $rack = Rack::create(['zone_id' => $zone->id, 'code' => 'A01', 'name' => 'Rack A01']);
        $this->location = Location::create(['rack_id' => $rack->id, 'code' => 'A01-01', 'name' => 'Bin 01']);
        $this->item1 = Item::create([
            'sku' => 'BRG-001', 'name' => 'Wireles Mouse', 'category_id' => $cat->id,
            'unit_id' => $this->unit->id, 'minimum_stock' => 5, 'maximum_stock' => 100, 'status' => 'active',
        ]);
        $this->item2 = Item::create([
            'sku' => 'BRG-002', 'name' => 'Mechanical Keyboard', 'category_id' => $cat->id,
            'unit_id' => $this->unit->id, 'minimum_stock' => 5, 'maximum_stock' => 100, 'status' => 'active',
        ]);
    }

    private function makePurchaseOrder(string $status = 'approved'): PurchaseOrder
    {
        $order = PurchaseOrder::create([
            'number' => 'PO-'.time().'-'.mt_rand(1000, 9999),
            'order_date' => today(),
            'expected_date' => today()->addDays(7),
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => $status,
            'created_by' => $this->admin->id,
            'submitted_by' => $status !== 'draft' ? $this->admin->id : null,
            'submitted_at' => $status !== 'draft' ? now() : null,
            'approved_by' => in_array($status, ['approved', 'partial', 'received', 'closed'], true) ? $this->admin->id : null,
            'approved_at' => in_array($status, ['approved', 'partial', 'received', 'closed'], true) ? now() : null,
        ]);

        $order->items()->create([
            'item_id' => $this->item1->id,
            'quantity' => 10,
            'received_quantity' => 0,
            'unit_id' => $this->unit->id,
            'unit_price' => 15000,
        ]);

        $order->items()->create([
            'item_id' => $this->item2->id,
            'quantity' => 5,
            'received_quantity' => 0,
            'unit_id' => $this->unit->id,
            'unit_price' => 50000,
        ]);

        return $order;
    }

    private function makePostedReceipt(PurchaseOrder $order, int $qty1, int $qty2): void
    {
        $receipt = GoodsReceipt::create([
            'number' => 'GR-'.time().'-'.mt_rand(1000, 9999),
            'transaction_date' => today(),
            'supplier_id' => $this->supplier->id,
            'purchase_order_id' => $order->id,
            'po_number' => $order->number,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'approved',
            'created_by' => $this->admin->id,
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        $receipt->receiptItems()->createMany([
            ['item_id' => $this->item1->id, 'quantity' => $qty1, 'unit_id' => $this->unit->id, 'location_id' => $this->location->id],
            ['item_id' => $this->item2->id, 'quantity' => $qty2, 'unit_id' => $this->unit->id, 'location_id' => $this->location->id],
        ]);

        InventoryService::postGoodsReceipt($receipt);
    }

    public function test_purchase_order_create_and_submit_and_approve(): void
    {
        $order = $this->makePurchaseOrder('draft');

        $this->assertEquals('draft', $order->status);
        $this->assertTrue($order->statusEnum()->canTransitionTo(PurchaseOrderStatus::Submitted));

        $order->update(['status' => PurchaseOrderStatus::Submitted->value, 'submitted_by' => $this->admin->id, 'submitted_at' => now()]);
        $this->assertEquals('submitted', $order->fresh()->status);
        $this->assertTrue($order->fresh()->statusEnum()->canTransitionTo(PurchaseOrderStatus::Approved));

        $order->update(['status' => PurchaseOrderStatus::Approved->value, 'approved_by' => $this->admin->id, 'approved_at' => now()]);
        $this->assertEquals('approved', $order->fresh()->status);
    }

    public function test_purchase_order_rejected_terminal(): void
    {
        $order = $this->makePurchaseOrder('submitted');
        $order->update(['status' => PurchaseOrderStatus::Rejected->value, 'rejected_by' => $this->admin->id, 'rejected_at' => now()]);
        $this->assertEquals('rejected', $order->fresh()->status);
        $this->assertFalse($order->fresh()->statusEnum()->canTransitionTo(PurchaseOrderStatus::Approved));
        $this->assertFalse($order->fresh()->statusEnum()->isOpen());
    }

    public function test_status_enum_transitions(): void
    {
        $this->assertTrue(PurchaseOrderStatus::Draft->canTransitionTo(PurchaseOrderStatus::Submitted));
        $this->assertTrue(PurchaseOrderStatus::Submitted->canTransitionTo(PurchaseOrderStatus::Approved));
        $this->assertTrue(PurchaseOrderStatus::Submitted->canTransitionTo(PurchaseOrderStatus::Rejected));
        $this->assertTrue(PurchaseOrderStatus::Approved->canTransitionTo(PurchaseOrderStatus::Partial));
        $this->assertTrue(PurchaseOrderStatus::Approved->canTransitionTo(PurchaseOrderStatus::Received));
        $this->assertTrue(PurchaseOrderStatus::Partial->canTransitionTo(PurchaseOrderStatus::Received));
        $this->assertTrue(PurchaseOrderStatus::Received->canTransitionTo(PurchaseOrderStatus::Closed));
        $this->assertFalse(PurchaseOrderStatus::Rejected->canTransitionTo(PurchaseOrderStatus::Submitted));
        $this->assertFalse(PurchaseOrderStatus::Closed->canTransitionTo(PurchaseOrderStatus::Received));
    }

    public function test_partial_receipt_updates_po_to_partial(): void
    {
        $order = $this->makePurchaseOrder('approved');
        $this->makePostedReceipt($order, 4, 2);

        $order->refresh()->load('items');
        $item1 = $order->items->firstWhere('item_id', $this->item1->id);

        $this->assertEquals(4, $item1->received_quantity);
        $this->assertEquals('partial', $order->fresh()->status);
        $this->assertEquals(6, $item1->remaining());
    }

    public function test_full_receipt_updates_po_to_received(): void
    {
        $order = $this->makePurchaseOrder('approved');
        $this->makePostedReceipt($order, 10, 5);

        $this->assertEquals('received', $order->fresh()->status);

        $order->refresh()->load('items');
        $this->assertTrue($order->items->every(fn ($item) => $item->remaining() === 0));
    }

    public function test_second_receipt_completes_partial_to_received(): void
    {
        $order = $this->makePurchaseOrder('approved');
        $this->makePostedReceipt($order, 5, 2);
        $this->assertEquals('partial', $order->fresh()->status);

        $this->makePostedReceipt($order, 5, 3);
        $this->assertEquals('received', $order->fresh()->status);
    }

    public function test_received_can_close(): void
    {
        $order = $this->makePurchaseOrder('approved');
        $this->makePostedReceipt($order, 10, 5);
        $this->assertEquals('received', $order->fresh()->status);

        $order->update(['status' => PurchaseOrderStatus::Closed->value, 'closed_by' => $this->admin->id, 'closed_at' => now()]);
        $this->assertEquals('closed', $order->fresh()->status);
        $this->assertFalse($order->fresh()->statusEnum()->isOpen());
    }

    public function test_document_number_service_generates_po(): void
    {
        $num = DocumentNumberService::generate('PO');
        $this->assertStringStartsWith('PO-', $num);
    }

    public function test_purchase_order_items_unique_constraint(): void
    {
        $order = $this->makePurchaseOrder('draft');
        $this->expectException(QueryException::class);
        $order->items()->create([
            'item_id' => $this->item1->id,
            'quantity' => 3,
            'received_quantity' => 0,
            'unit_id' => $this->unit->id,
            'unit_price' => 10000,
        ]);
    }

    public function test_is_posted_like_helper(): void
    {
        $this->assertFalse(PurchaseOrderStatus::Draft->isPostedLike());
        $this->assertFalse(PurchaseOrderStatus::Submitted->isPostedLike());
        $this->assertTrue(PurchaseOrderStatus::Approved->isPostedLike());
        $this->assertTrue(PurchaseOrderStatus::Partial->isPostedLike());
        $this->assertTrue(PurchaseOrderStatus::Received->isPostedLike());
        $this->assertTrue(PurchaseOrderStatus::Closed->isPostedLike());
    }
}
