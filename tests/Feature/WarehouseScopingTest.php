<?php

namespace Tests\Feature;

use App\Livewire\Admin\UserIndex;
use App\Livewire\Inventory\StockOnHandIndex;
use App\Livewire\Layout\WarehouseSwitcher;
use App\Models\Category;
use App\Models\Item;
use App\Models\Location;
use App\Models\Role;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WarehouseScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function scopedStaff(string $warehouseCode): User
    {
        $user = User::create([
            'name' => 'Scoped Staff',
            'email' => 'scoped'.uniqid().'@stock.test',
            'password' => bcrypt('password'),
            'status' => 'active',
            'all_warehouses' => false,
        ]);

        $user->roles()->sync([Role::where('slug', 'warehouse_staff')->firstOrFail()->id]);
        $user->warehouses()->sync([Warehouse::where('code', $warehouseCode)->value('id')]);

        return $user;
    }

    private function makeItem(string $sku, string $warehouseCode, string $locationCode): Item
    {
        $item = Item::create([
            'sku' => $sku,
            'name' => $sku,
            'category_id' => Category::firstOrFail()->id,
            'unit_id' => Unit::firstOrFail()->id,
            'minimum_stock' => 0,
            'maximum_stock' => 0,
            'status' => 'active',
        ]);

        StockBalance::create([
            'item_id' => $item->id,
            'warehouse_id' => Warehouse::where('code', $warehouseCode)->value('id'),
            'location_id' => Location::where('code', $locationCode)->value('id'),
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
            'quantity_quarantine' => 0,
        ]);

        return $item;
    }

    public function test_user_access_helpers(): void
    {
        $user = $this->scopedStaff('WH-JKT');
        $jkt = Warehouse::where('code', 'WH-JKT')->firstOrFail();
        $bdg = Warehouse::where('code', 'WH-BDG')->firstOrFail();

        $this->assertFalse($user->allowsAllWarehouses());
        $this->assertTrue($user->canAccessWarehouse($jkt->id));
        $this->assertFalse($user->canAccessWarehouse($bdg->id));
        $this->assertSame([$jkt->id], $user->accessibleWarehouseIds());

        $admin = User::where('email', 'admin@stock.test')->firstOrFail();
        $this->assertTrue($admin->allowsAllWarehouses());
    }

    public function test_stock_on_hand_is_scoped_to_accessible_warehouses(): void
    {
        $user = $this->scopedStaff('WH-JKT');
        $this->makeItem('SCOPE-JKT', 'WH-JKT', 'A01-01');
        $this->makeItem('SCOPE-BDG', 'WH-BDG', 'C01-01');

        $this->actingAs($user);

        Livewire::test(StockOnHandIndex::class)
            ->set('search', 'SCOPE-JKT')
            ->assertSee('SCOPE-JKT');

        Livewire::test(StockOnHandIndex::class)
            ->set('search', 'SCOPE-BDG')
            ->assertDontSee('SCOPE-BDG');
    }

    public function test_warehouse_switcher_lists_only_accessible(): void
    {
        $user = $this->scopedStaff('WH-JKT');
        $this->actingAs($user);

        Livewire::test(WarehouseSwitcher::class)
            ->assertSee('Warehouse Jakarta')
            ->assertDontSee('Warehouse Bandung');
    }

    public function test_inaccessible_session_warehouse_is_reset(): void
    {
        $user = $this->scopedStaff('WH-JKT');
        $bdg = Warehouse::where('code', 'WH-BDG')->firstOrFail();

        session(['active_warehouse_id' => $bdg->id]);

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $this->assertNull(session('active_warehouse_id'));
    }

    public function test_admin_can_assign_warehouses_to_user(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();
        $staff = $this->scopedStaff('WH-JKT');
        $bdg = Warehouse::where('code', 'WH-BDG')->firstOrFail();

        $this->actingAs($admin);

        Livewire::test(UserIndex::class)
            ->call('openEdit', $staff->id)
            ->set('allWarehouses', false)
            ->set('selectedWarehouses', [$bdg->id])
            ->call('save');

        $this->assertTrue($staff->fresh()->warehouses->contains('id', $bdg->id));
    }

    public function test_api_warehouses_only_returns_accessible(): void
    {
        $user = $this->scopedStaff('WH-JKT');
        $user->roles()->sync([Role::where('slug', 'warehouse_staff')->firstOrFail()->id]);

        $this->actingAs($user);

        $response = $this->getJson('/api/warehouses')->assertOk();

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Warehouse Jakarta', $names);
        $this->assertNotContains('Warehouse Bandung', $names);
    }
}
