<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Enums\TransferStatus;
use App\Models\Category;
use App\Models\Item;
use App\Models\Location;
use App\Models\Rack;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StockTransferTest extends TestCase
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

        $roleId = DB::table('roles')->insertGetId(['name' => 'Administrator', 'slug' => 'admin', 'is_system' => true, 'created_at' => now(), 'updated_at' => now()]);
        $permId = DB::table('permissions')->insertGetId(['slug' => 'admin', 'name' => 'All', 'group' => 'system', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('role_permission')->insert(['role_id' => $roleId, 'permission_id' => $permId]);

        $this->admin = User::factory()->create();
        DB::table('user_role')->insert(['user_id' => $this->admin->id, 'role_id' => $roleId]);
        $this->actingAs($this->admin);

        $this->unit = Unit::create(['code' => 'PCS', 'name' => 'Pieces']);
        $cat = Category::create(['code' => 'ELEC', 'name' => 'Electronics', 'status' => 'active']);
        $this->warehouseA = Warehouse::create(['code' => 'WH-A', 'name' => 'Warehouse A', 'status' => 'active']);
        $this->warehouseB = Warehouse::create(['code' => 'WH-B', 'name' => 'Warehouse B', 'status' => 'active']);
        $zoneA = Zone::create(['warehouse_id' => $this->warehouseA->id, 'code' => 'ZA', 'name' => 'Zone A']);
        $zoneB = Zone::create(['warehouse_id' => $this->warehouseB->id, 'code' => 'ZB', 'name' => 'Zone B']);
        $rackA = Rack::create(['zone_id' => $zoneA->id, 'code' => 'RA', 'name' => 'Rack A01']);
        $rackB = Rack::create(['zone_id' => $zoneB->id, 'code' => 'RB', 'name' => 'Rack B01']);
        $this->locationA = Location::create(['rack_id' => $rackA->id, 'code' => 'A01-01', 'name' => 'Bin A01']);
        $this->locationB = Location::create(['rack_id' => $rackB->id, 'code' => 'B01-01', 'name' => 'Bin B01']);
        $this->item = Item::create([
            'sku' => 'BRG-TR-1', 'name' => 'Transfer Item', 'category_id' => $cat->id,
            'unit_id' => $this->unit->id, 'minimum_stock' => 5, 'maximum_stock' => 500, 'status' => 'active',
        ]);
    }

    private function makeTransfer(int $quantity, int $openingQty = 100, string $status = TransferStatus::Approved->value): StockTransfer
    {
        LedgerService::record($this->item->id, $this->warehouseA->id, $this->locationA->id, TransactionType::Opening, 'opening', 0, $openingQty, 0);

        $transfer = StockTransfer::create([
            'number' => 'TR-'.time().'-'.mt_rand(1000, 9999),
            'transfer_date' => today(),
            'from_warehouse_id' => $this->warehouseA->id,
            'from_location_id' => $this->locationA->id,
            'to_warehouse_id' => $this->warehouseB->id,
            'to_location_id' => $this->locationB->id,
            'status' => $status,
            'created_by' => $this->admin->id,
            'approved_by' => in_array($status, [TransferStatus::Approved->value, TransferStatus::InTransit->value, TransferStatus::Received->value, TransferStatus::Completed->value], true) ? $this->admin->id : null,
            'approved_at' => in_array($status, [TransferStatus::Approved->value, TransferStatus::InTransit->value, TransferStatus::Received->value, TransferStatus::Completed->value], true) ? now() : null,
        ]);

        $transfer->items()->create([
            'item_id' => $this->item->id,
            'quantity' => $quantity,
            'unit_id' => $this->unit->id,
        ]);

        return $transfer;
    }

    public function test_transfer_dispatch_and_receive_moves_stock(): void
    {
        $transfer = $this->makeTransfer(30, 100);

        InventoryService::dispatchStockTransfer($transfer);

        $balanceA = StockBalance::where('item_id', $this->item->id)->where('warehouse_id', $this->warehouseA->id)->where('location_id', $this->locationA->id)->value('quantity_on_hand');
        $this->assertEquals(70, $balanceA);
        $this->assertTrue(StockMovement::where('transaction_type', TransactionType::TransferOut->value)->where('quantity_out', 30)->exists());
        $this->assertEquals(TransferStatus::InTransit->value, $transfer->fresh()->status);

        InventoryService::receiveStockTransfer($transfer->fresh());

        $balanceB = StockBalance::where('item_id', $this->item->id)->where('warehouse_id', $this->warehouseB->id)->where('location_id', $this->locationB->id)->value('quantity_on_hand');
        $this->assertEquals(30, $balanceB);
        $this->assertTrue(StockMovement::where('transaction_type', TransactionType::TransferIn->value)->where('quantity_in', 30)->exists());
        $this->assertEquals(TransferStatus::Received->value, $transfer->fresh()->status);
    }

    public function test_insufficient_stock_dispatch_throws(): void
    {
        $transfer = $this->makeTransfer(30, 10);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient stock');
        InventoryService::dispatchStockTransfer($transfer);

        $this->assertEquals(TransferStatus::Approved->value, $transfer->fresh()->status);
    }

    public function test_cannot_dispatch_before_approved(): void
    {
        $transfer = $this->makeTransfer(10, 100, TransferStatus::Draft->value);

        $this->expectException(\RuntimeException::class);
        InventoryService::dispatchStockTransfer($transfer);
    }

    public function test_cannot_receive_before_in_transit(): void
    {
        $transfer = $this->makeTransfer(10, 100, TransferStatus::Approved->value);

        $this->expectException(\RuntimeException::class);
        InventoryService::receiveStockTransfer($transfer);
    }

    public function test_transfer_status_guards_via_enum(): void
    {
        $this->assertTrue(TransferStatus::Draft->canTransitionTo(TransferStatus::Requested));
        $this->assertTrue(TransferStatus::Requested->canTransitionTo(TransferStatus::Approved));
        $this->assertTrue(TransferStatus::Requested->canTransitionTo(TransferStatus::Rejected));
        $this->assertTrue(TransferStatus::Approved->canTransitionTo(TransferStatus::InTransit));
        $this->assertTrue(TransferStatus::InTransit->canTransitionTo(TransferStatus::Received));
        $this->assertTrue(TransferStatus::Received->canTransitionTo(TransferStatus::Completed));
        $this->assertFalse(TransferStatus::Completed->canTransitionTo(TransferStatus::Draft));
        $this->assertFalse(TransferStatus::Rejected->isOpen());
        $this->assertFalse(TransferStatus::Completed->isOpen());
        $this->assertTrue(TransferStatus::Draft->isOpen());
        $this->assertFalse(TransferStatus::Draft->canTransitionTo(TransferStatus::Approved));
    }
}
