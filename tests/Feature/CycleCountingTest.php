<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\StockOpname;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CycleCountingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_command_generates_cycle_opnames_per_zone(): void
    {
        Setting::set('inventory.cycle_count_enabled', '1', 'inventory');

        $this->artisan('inventory:cycle-count')->assertSuccessful();

        $zoneCount = Zone::count();

        $this->assertGreaterThan(0, $zoneCount);
        $this->assertEquals(
            $zoneCount,
            StockOpname::where('type', 'cycle')->count(),
        );
        $this->assertNotNull(StockOpname::where('type', 'cycle')->first()->zone_id);
    }

    public function test_command_skips_when_disabled(): void
    {
        Setting::set('inventory.cycle_count_enabled', '0', 'inventory');

        $this->artisan('inventory:cycle-count')->assertSuccessful();

        $this->assertEquals(0, StockOpname::where('type', 'cycle')->count());
    }

    public function test_form_can_create_cycle_opname(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();
        $zone = Zone::firstOrFail();

        Livewire::actingAs($admin)->test('transactions.stock-opname-form')
            ->set('opname_date', now()->format('Y-m-d'))
            ->set('warehouse_id', (string) $zone->warehouse_id)
            ->set('type', 'cycle')
            ->set('zone_id', (string) $zone->id)
            ->call('save');

        $opname = StockOpname::where('type', 'cycle')->latest('id')->first();

        $this->assertNotNull($opname);
        $this->assertEquals($zone->id, $opname->zone_id);
    }
}
