<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Item;
use App\Models\Location;
use App\Models\Rack;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Services\Inventory\ExpiryService;
use App\Services\Inventory\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ExpiryTrackingTest extends TestCase
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
        $this->admin = User::factory()->create();
        DB::table('user_role')->insert(['user_id' => $this->admin->id, 'role_id' => $roleId]);
        $this->actingAs($this->admin);

        $this->unit = Unit::create(['code' => 'PCS', 'name' => 'Pieces']);
        $this->category = Category::create(['code' => 'ELEC', 'name' => 'Electronics', 'status' => 'active']);
        $this->warehouse = Warehouse::create(['code' => 'WH-EXP', 'name' => 'Expiry Warehouse', 'status' => 'active']);
        $zone = Zone::create(['warehouse_id' => $this->warehouse->id, 'code' => 'Z-EXP', 'name' => 'Zone Exp']);
        $rack = Rack::create(['zone_id' => $zone->id, 'code' => 'R-EXP', 'name' => 'Rack Exp']);
        $this->location = Location::create(['rack_id' => $rack->id, 'code' => 'E01-01', 'name' => 'Bin Exp']);
    }

    protected function makeItem(string $sku): Item
    {
        return Item::create([
            'sku' => $sku,
            'name' => 'Item '.$sku,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'minimum_stock' => 0,
            'maximum_stock' => 1000,
            'status' => 'active',
        ]);
    }

    protected function receive(Item $item, int $qty, ?string $expiry, ?string $batch = null): void
    {
        LedgerService::record(
            (int) $item->id,
            (int) $this->warehouse->id,
            (int) $this->location->id,
            TransactionType::Incoming,
            'incoming',
            1,
            $qty,
            0,
            $batch,
            $expiry,
        );
    }

    public function test_incoming_with_expiry_in_five_days_is_listed_under_expiring_30(): void
    {
        $item = $this->makeItem('EXP-FIVE');
        $expiry = Carbon::today()->addDays(5)->toDateString();
        $this->receive($item, 10, $expiry, 'BATCH-5');

        $rows = ExpiryService::rows('expiring_30')->get();

        $this->assertCount(1, $rows);
        $this->assertSame('EXP-FIVE', $rows->first()->sku);
        $this->assertSame('BATCH-5', $rows->first()->batch_number);
    }

    public function test_expired_batch_is_listed_under_expired(): void
    {
        $item = $this->makeItem('EXP-OLD');
        $this->receive($item, 4, Carbon::today()->subDays(2)->toDateString(), 'BATCH-OLD');

        $expired = ExpiryService::rows('expired')->get();
        $expiring = ExpiryService::rows('expiring_30')->get();

        $this->assertCount(1, $expired);
        $this->assertSame('EXP-OLD', $expired->first()->sku);
        $this->assertCount(0, $expiring);
    }

    public function test_counts_returns_correct_group_totals(): void
    {
        $this->receive($this->makeItem('C-EXPIRED'), 1, Carbon::today()->subDays(1)->toDateString());
        $this->receive($this->makeItem('C-30'), 2, Carbon::today()->addDays(10)->toDateString());
        $this->receive($this->makeItem('C-90'), 3, Carbon::today()->addDays(60)->toDateString());
        $this->receive($this->makeItem('C-VALID'), 5, Carbon::today()->addDays(200)->toDateString());

        $counts = ExpiryService::counts();

        $this->assertSame(1, $counts['expired']);
        $this->assertSame(1, $counts['expiring_30']);
        $this->assertSame(1, $counts['expiring_90']);
        $this->assertSame(1, $counts['valid']);
        $this->assertSame(4, $counts['total']);
    }

    public function test_expiry_report_filters_by_expired_status(): void
    {
        $this->receive($this->makeItem('R-OLD'), 1, Carbon::today()->subDays(3)->toDateString());
        $this->receive($this->makeItem('R-NEW'), 1, Carbon::today()->addDays(200)->toDateString());

        Livewire::actingAs($this->admin)->test('reports.expiry-report')
            ->set('statusFilter', 'expired')
            ->assertSee('R-OLD')
            ->assertDontSee('R-NEW');
    }
}
