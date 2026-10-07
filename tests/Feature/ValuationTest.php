<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Livewire\Reports\ValuationReport;
use App\Models\Category;
use App\Models\InventoryValuation;
use App\Models\Item;
use App\Models\Location;
use App\Models\Rack;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Services\Inventory\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ValuationTest extends TestCase
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
        DB::table('permissions')->insert([
            ['slug' => 'reports.view', 'name' => 'Reports View', 'group' => 'reports', 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'reports.export', 'name' => 'Reports Export', 'group' => 'reports', 'created_at' => now(), 'updated_at' => now()],
        ]);

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
            'sku' => 'BRG-VAL-1', 'name' => 'Valuation Item', 'category_id' => $cat->id,
            'unit_id' => $this->unit->id, 'minimum_stock' => 0, 'maximum_stock' => 1000, 'status' => 'active', 'cost' => 0,
        ]);
    }

    public function test_moving_average_on_successive_incomings(): void
    {
        LedgerService::record($this->item->id, $this->warehouse->id, $this->location->id, TransactionType::Incoming, 'test', 1, 10, 0, null, null, null, 100);
        LedgerService::record($this->item->id, $this->warehouse->id, $this->location->id, TransactionType::Incoming, 'test', 2, 10, 0, null, null, null, 200);

        $valuation = InventoryValuation::where('item_id', $this->item->id)->where('warehouse_id', $this->warehouse->id)->first();
        $this->assertNotNull($valuation);
        $this->assertEquals(20, (int) $valuation->quantity);
        $this->assertEqualsWithDelta(150.0, (float) $valuation->average_cost, 0.01);
        $this->assertEqualsWithDelta(3000.0, (float) $valuation->total_value, 0.01);
    }

    public function test_outgoing_uses_average_cost(): void
    {
        LedgerService::record($this->item->id, $this->warehouse->id, $this->location->id, TransactionType::Incoming, 'test', 1, 10, 0, null, null, null, 100);
        LedgerService::record($this->item->id, $this->warehouse->id, $this->location->id, TransactionType::Incoming, 'test', 2, 10, 0, null, null, null, 200);
        $movement = LedgerService::record($this->item->id, $this->warehouse->id, $this->location->id, TransactionType::Outgoing, 'test', 3, 0, 5, null, null, null, null);

        $this->assertEqualsWithDelta(150.0, (float) $movement->unit_cost, 0.01);
        $this->assertEqualsWithDelta(750.0, (float) $movement->total_cost, 0.01);

        $valuation = InventoryValuation::where('item_id', $this->item->id)->where('warehouse_id', $this->warehouse->id)->first();
        $this->assertEquals(15, (int) $valuation->quantity);
        $this->assertEqualsWithDelta(150.0, (float) $valuation->average_cost, 0.01);
        $this->assertEqualsWithDelta(2250.0, (float) $valuation->total_value, 0.01);
    }

    public function test_value_zero_after_depleting_stock(): void
    {
        LedgerService::record($this->item->id, $this->warehouse->id, $this->location->id, TransactionType::Incoming, 'test', 1, 10, 0, null, null, null, 100);
        LedgerService::record($this->item->id, $this->warehouse->id, $this->location->id, TransactionType::Incoming, 'test', 2, 10, 0, null, null, null, 200);
        LedgerService::record($this->item->id, $this->warehouse->id, $this->location->id, TransactionType::Outgoing, 'test', 3, 0, 5, null, null, null, null);
        LedgerService::record($this->item->id, $this->warehouse->id, $this->location->id, TransactionType::Outgoing, 'test', 4, 0, 15, null, null, null, null);

        $valuation = InventoryValuation::where('item_id', $this->item->id)->where('warehouse_id', $this->warehouse->id)->first();
        $this->assertEquals(0, (int) $valuation->quantity);
        $this->assertEqualsWithDelta(0.0, (float) $valuation->total_value, 0.01);
        $this->assertEqualsWithDelta(150.0, (float) $valuation->average_cost, 0.01);
    }

    public function test_incoming_without_unit_cost_falls_back_to_item_cost(): void
    {
        $this->item->update(['cost' => 50]);
        LedgerService::record($this->item->id, $this->warehouse->id, $this->location->id, TransactionType::Incoming, 'test', 1, 10, 0, null, null, null, null);

        $valuation = InventoryValuation::where('item_id', $this->item->id)->where('warehouse_id', $this->warehouse->id)->first();
        $this->assertEqualsWithDelta(50.0, (float) $valuation->average_cost, 0.01);
        $this->assertEqualsWithDelta(500.0, (float) $valuation->total_value, 0.01);
    }

    public function test_valuation_report_renders_and_totals(): void
    {
        LedgerService::record($this->item->id, $this->warehouse->id, $this->location->id, TransactionType::Incoming, 'test', 1, 10, 0, null, null, null, 100);

        Livewire::test(ValuationReport::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.reports.valuation-report');
    }
}
