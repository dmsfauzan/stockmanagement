<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_command_creates_low_stock_notifications(): void
    {
        Notification::query()->delete();

        $this->artisan('inventory:alerts')->assertSuccessful();

        $this->assertTrue(
            Notification::whereIn('type', ['stock.low', 'stock.out'])->exists(),
            'Expected low/out stock notifications',
        );

        $supervisor = User::where('email', 'supervisor@stock.test')->firstOrFail();
        $this->assertTrue(
            Notification::where('user_id', $supervisor->id)->whereIn('type', ['stock.low', 'stock.out'])->exists(),
        );
    }

    public function test_command_creates_expiry_notifications(): void
    {
        Notification::query()->delete();

        $this->artisan('inventory:alerts')->assertSuccessful();

        $this->assertTrue(
            Notification::where('type', 'stock.expiring')->exists(),
            'Expected expiry notifications from seeded batch',
        );
    }

    public function test_command_is_idempotent_until_dedup_window(): void
    {
        Notification::query()->delete();

        $this->artisan('inventory:alerts')->assertSuccessful();
        $firstCount = Notification::count();
        $this->assertGreaterThan(0, $firstCount);

        $this->artisan('inventory:alerts')->assertSuccessful();
        $this->assertSame($firstCount, Notification::count(), 'Second run should not duplicate notifications');
    }

    public function test_force_option_bypasses_dedup(): void
    {
        Notification::query()->delete();

        $this->artisan('inventory:alerts')->assertSuccessful();
        $firstCount = Notification::count();

        $this->artisan('inventory:alerts', ['--force' => true])->assertSuccessful();
        $this->assertGreaterThan($firstCount, Notification::count());
    }
}
