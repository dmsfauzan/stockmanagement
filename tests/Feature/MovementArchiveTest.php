<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MovementArchiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function makeMovement(?Carbon $createdAt = null): int
    {
        $item = DB::table('items')->first();
        $warehouse = DB::table('warehouses')->first();
        $location = DB::table('locations')->first();

        return (int) DB::table('stock_movements')->insertGetId([
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'location_id' => $location->id,
            'transaction_type' => 'incoming',
            'reference_type' => 'test',
            'reference_id' => 999,
            'quantity_in' => 5,
            'quantity_out' => 0,
            'balance_after' => 5,
            'batch_number' => 'B-TEST',
            'serial_number' => null,
            'expiry_date' => null,
            'unit_cost' => 100,
            'total_cost' => 500,
            'notes' => 'archive test',
            'created_by' => 1,
            'created_at' => $createdAt ?? Carbon::now()->subDays(400),
        ]);
    }

    public function test_dry_run_reports_without_moving(): void
    {
        Setting::set('inventory.archive_enabled', '1', 'inventory');
        Setting::set('inventory.archive_days', '365', 'inventory');

        $id = $this->makeMovement();

        $this->artisan('inventory:archive', ['--days' => 365, '--dry-run' => true])->assertSuccessful();

        $this->assertTrue(DB::table('stock_movements')->where('id', $id)->exists());
        $this->assertFalse(DB::table('stock_movement_archives')->where('id', $id)->exists());
    }

    public function test_command_moves_rows_and_respects_cutoff(): void
    {
        $recent = $this->makeMovement(Carbon::now()->subDays(10));
        $old = $this->makeMovement(Carbon::now()->subDays(400));

        $this->artisan('inventory:archive', ['--days' => 365])->assertSuccessful();

        $this->assertTrue(DB::table('stock_movements')->where('id', $recent)->exists());
        $this->assertFalse(DB::table('stock_movements')->where('id', $old)->exists());
        $this->assertTrue(DB::table('stock_movement_archives')->where('id', $old)->exists());
    }

    public function test_command_skips_when_disabled(): void
    {
        Setting::set('inventory.archive_enabled', '0', 'inventory');

        $id = $this->makeMovement();

        $this->artisan('inventory:archive')->assertSuccessful();

        $this->assertTrue(DB::table('stock_movements')->where('id', $id)->exists());
    }

    public function test_force_flag_overrides_disabled_setting(): void
    {
        Setting::set('inventory.archive_enabled', '0', 'inventory');

        $id = $this->makeMovement(Carbon::now()->subDays(400));

        $this->artisan('inventory:archive', ['--days' => 365, '--force' => true])->assertSuccessful();

        $this->assertFalse(DB::table('stock_movements')->where('id', $id)->exists());
        $this->assertTrue(DB::table('stock_movement_archives')->where('id', $id)->exists());
    }
}
