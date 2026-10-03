<?php

namespace Tests\Feature;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Item;
use App\Models\Location;
use App\Models\Rack;
use App\Models\StockAdjustment;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StockAdjustmentTest extends TestCase
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

    private function makeApprovedAdjustment(int $systemQty, int $actualQty, string $status = 'approved'): StockAdjustment
    {
        LedgerService::record($this->item->id, $this->warehouse->id, $this->location->id, TransactionType::Opening, 'opening', 0, $systemQty, 0);

        $adj = StockAdjustment::create([
            'number' => 'ADJ-'.time().'-'.mt_rand(1000, 9999),
            'transaction_date' => today(),
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->location->id,
            'reason' => 'Stock Count Error',
            'status' => $status,
            'created_by' => $this->admin->id,
            'approved_by' => $status === 'approved' ? $this->admin->id : null,
            'approved_at' => $status === 'approved' ? now() : null,
        ]);

        $adj->items()->create([
            'item_id' => $this->item->id,
            'system_quantity' => $systemQty,
            'actual_quantity' => $actualQty,
            'difference' => $actualQty - $systemQty,
        ]);

        return $adj;
    }

    public function test_adjustment_out_when_actual_less_than_system(): void
    {
        $adj = $this->makeApprovedAdjustment(100, 97);
        InventoryService::postStockAdjustment($adj);

        $this->assertEquals(97, StockBalance::first()->quantity_on_hand);
        $this->assertTrue(StockMovement::where('transaction_type', TransactionType::AdjustmentOut->value)->where('quantity_out', 3)->exists());
        $this->assertEquals('posted', $adj->fresh()->status);
    }

    public function test_adjustment_in_when_actual_greater_than_system(): void
    {
        $adj = $this->makeApprovedAdjustment(50, 55);
        InventoryService::postStockAdjustment($adj);

        $this->assertEquals(55, StockBalance::first()->quantity_on_hand);
        $this->assertTrue(StockMovement::where('transaction_type', TransactionType::AdjustmentIn->value)->where('quantity_in', 5)->exists());
    }

    public function test_draft_cannot_be_posted(): void
    {
        $adj = $this->makeApprovedAdjustment(100, 97, 'draft');

        $this->expectException(\RuntimeException::class);
        InventoryService::postStockAdjustment($adj);
    }

    public function test_submitted_to_approved_to_posted_works(): void
    {
        $adj = $this->makeApprovedAdjustment(10, 15, 'submitted');
        $this->assertTrue($adj->fresh()->statusEnum()->canTransitionTo(TransactionStatus::Approved));

        $adj->update(['status' => TransactionStatus::Approved->value, 'approved_by' => $this->admin->id, 'approved_at' => now()]);
        InventoryService::postStockAdjustment($adj->fresh());
        $this->assertEquals('posted', $adj->fresh()->status);
    }

    public function test_posting_twice_is_blocked(): void
    {
        $adj = $this->makeApprovedAdjustment(100, 90);
        InventoryService::postStockAdjustment($adj);

        $this->expectException(\RuntimeException::class);
        InventoryService::postStockAdjustment($adj->fresh());
    }
}
