<?php

namespace Tests\Feature;

use App\Enums\OpnameStatus;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Item;
use App\Models\Location;
use App\Models\Rack;
use App\Models\StockAdjustment;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StockOpnameTest extends TestCase
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

        $roleId = DB::table('roles')->insertGetId(['name' => 'Administrator', 'slug' => 'admin', 'is_system' => true, 'created_at' => now(), 'updated_at' => now()]);
        $permId = DB::table('permissions')->insertGetId(['slug' => 'admin', 'name' => 'All', 'group' => 'system', 'created_at' => now(), 'updated_at' => now()]);
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

    private function makeSubmittedOpname(int $systemQty, int $physicalQty): StockOpname
    {
        LedgerService::record($this->item->id, $this->warehouse->id, $this->location->id, TransactionType::Opening, 'opening', 0, $systemQty, 0);

        $opname = StockOpname::create([
            'number' => 'OPN-'.time().'-'.mt_rand(1000, 9999),
            'opname_date' => today(),
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->location->id,
            'status' => OpnameStatus::Submitted->value,
            'created_by' => $this->admin->id,
            'submitted_by' => $this->admin->id,
            'submitted_at' => now(),
        ]);

        $opname->items()->create([
            'item_id' => $this->item->id,
            'system_quantity' => $systemQty,
            'physical_quantity' => $physicalQty,
            'difference' => $physicalQty - $systemQty,
        ]);

        $opname->update([
            'status' => OpnameStatus::Approved->value,
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        return $opname->fresh('items');
    }

    public function test_opname_adjustment_out_when_physical_less_than_system(): void
    {
        $opname = $this->makeSubmittedOpname(100, 97);
        InventoryService::completeStockOpname($opname->fresh());

        $this->assertEquals(97, StockBalance::first()->quantity_on_hand);
        $this->assertTrue(StockMovement::where('transaction_type', TransactionType::AdjustmentOut->value)->where('quantity_out', 3)->exists());
        $this->assertEquals('completed', $opname->fresh()->status);
        $this->assertNotNull($opname->fresh()->stock_adjustment_id);
    }

    public function test_opname_adjustment_in_when_physical_greater_than_system(): void
    {
        $opname = $this->makeSubmittedOpname(50, 55);
        InventoryService::completeStockOpname($opname->fresh());

        $this->assertEquals(55, StockBalance::first()->quantity_on_hand);
        $this->assertTrue(StockMovement::where('transaction_type', TransactionType::AdjustmentIn->value)->where('quantity_in', 5)->exists());
        $this->assertEquals('completed', $opname->fresh()->status);
    }

    public function test_status_guards(): void
    {
        $this->assertTrue(OpnameStatus::Draft->canTransitionTo(OpnameStatus::Submitted));
        $this->assertFalse(OpnameStatus::Draft->canTransitionTo(OpnameStatus::Approved));
        $this->assertTrue(OpnameStatus::Submitted->canTransitionTo(OpnameStatus::Approved));
        $this->assertTrue(OpnameStatus::Approved->canTransitionTo(OpnameStatus::Completed));
        $this->assertFalse(OpnameStatus::Completed->canTransitionTo(OpnameStatus::Draft));
    }

    public function test_opname_incomplete_throws(): void
    {
        $opname = StockOpname::create([
            'number' => 'OPN-'.time().'-'.mt_rand(1000, 9999),
            'opname_date' => today(),
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->location->id,
            'status' => OpnameStatus::Submitted->value,
            'created_by' => $this->admin->id,
            'submitted_by' => $this->admin->id,
            'submitted_at' => now(),
        ]);
        $opname->items()->create(['item_id' => $this->item->id, 'system_quantity' => 10, 'physical_quantity' => null, 'difference' => 0]);
        $opname->update(['status' => OpnameStatus::Approved->value, 'approved_by' => $this->admin->id, 'approved_at' => now()]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Opname belum lengkap');
        InventoryService::completeStockOpname($opname->fresh());
    }

    public function test_zero_variance_completes_without_adjustment(): void
    {
        LedgerService::record($this->item->id, $this->warehouse->id, $this->location->id, TransactionType::Opening, 'opening', 0, 50, 0);

        $opname = StockOpname::create([
            'number' => 'OPN-'.time().'-'.mt_rand(1000, 9999),
            'opname_date' => today(),
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->location->id,
            'status' => OpnameStatus::Submitted->value,
            'created_by' => $this->admin->id,
            'submitted_by' => $this->admin->id,
            'submitted_at' => now(),
        ]);
        $opname->items()->create(['item_id' => $this->item->id, 'system_quantity' => 50, 'physical_quantity' => 50, 'difference' => 0]);
        $opname->update(['status' => OpnameStatus::Approved->value, 'approved_by' => $this->admin->id, 'approved_at' => now()]);
        $beforeAdjustments = StockAdjustment::count();
        InventoryService::completeStockOpname($opname->fresh());
        $this->assertEquals('completed', $opname->fresh()->status);
        $this->assertNull($opname->fresh()->stock_adjustment_id);
        $this->assertEquals($beforeAdjustments, StockAdjustment::count());
        $this->assertEquals(50, StockBalance::first()->quantity_on_hand);
    }
}
