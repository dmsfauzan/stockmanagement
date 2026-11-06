<?php

namespace Tests\Feature;

use App\Enums\OpnameStatus;
use App\Livewire\Transactions\StockOpnameCount;
use App\Models\Category;
use App\Models\Item;
use App\Models\Location;
use App\Models\Rack;
use App\Models\StockOpname;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression coverage for the opname counting flaw: item rows submitted by
 * the browser must belong to the opname being submitted.
 */
class StockOpnameCountBindingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouse;

    private Location $location;

    private Unit $unit;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $roleId = DB::table('roles')->insertGetId(['name' => 'Administrator', 'slug' => 'admin', 'is_system' => true, 'created_at' => now(), 'updated_at' => now()]);
        $permId = DB::table('permissions')->insertGetId(['slug' => 'admin', 'name' => 'All', 'group' => 'system', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('role_permission')->insert(['role_id' => $roleId, 'permission_id' => $permId]);

        $this->admin = User::factory()->create(['all_warehouses' => true]);
        DB::table('user_role')->insert(['user_id' => $this->admin->id, 'role_id' => $roleId]);
        $this->actingAs($this->admin);

        $this->unit = Unit::create(['code' => 'PCS', 'name' => 'Pieces']);
        $this->category = Category::create(['code' => 'ELEC', 'name' => 'Electronics', 'status' => 'active']);
        $this->warehouse = Warehouse::create(['code' => 'WH-QA', 'name' => 'QA Warehouse', 'status' => 'active']);
        $zone = Zone::create(['warehouse_id' => $this->warehouse->id, 'code' => 'Z-A', 'name' => 'Zone A']);
        $rack = Rack::create(['zone_id' => $zone->id, 'code' => 'A01', 'name' => 'Rack A01']);
        $this->location = Location::create(['rack_id' => $rack->id, 'code' => 'A01-01', 'name' => 'Bin 01']);
    }

    private function makeItem(string $sku): Item
    {
        return Item::create([
            'sku' => $sku,
            'name' => $sku,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'minimum_stock' => 0,
            'maximum_stock' => 0,
            'status' => 'active',
        ]);
    }

    private function makeOpname(string $number): StockOpname
    {
        return StockOpname::create([
            'number' => $number,
            'opname_date' => today(),
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->location->id,
            'status' => OpnameStatus::Counting->value,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_submit_cannot_update_items_from_another_opname(): void
    {
        $target = $this->makeOpname('OPN-QA-TARGET');
        $targetItem = $target->items()->create([
            'item_id' => $this->makeItem('QA-TARGET')->id,
            'system_quantity' => 10,
            'physical_quantity' => null,
            'difference' => 0,
        ]);

        $other = $this->makeOpname('OPN-QA-OTHER');
        $foreignItem = $other->items()->create([
            'item_id' => $this->makeItem('QA-FOREIGN')->id,
            'system_quantity' => 20,
            'physical_quantity' => 20,
            'difference' => 0,
        ]);

        // A tampered browser payload injects the foreign row id.
        Livewire::test(StockOpnameCount::class, ['opname' => $target->id])
            ->set('items', [[
                'id' => $foreignItem->id,
                'item_id' => $foreignItem->item_id,
                'system_quantity' => 20,
                'physical_quantity' => 999,
                'reason' => 'tampered',
                'notes' => 'tampered',
            ]])
            ->call('submit');

        $this->assertSame(20, (int) $foreignItem->fresh()->physical_quantity);
        $this->assertNull($foreignItem->fresh()->reason);
        $this->assertSame(OpnameStatus::Submitted->value, $target->fresh()->status);
    }
}
