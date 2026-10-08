<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_seeder_does_not_reset_existing_password(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@stock.test')->firstOrFail();
        $admin->update(['password' => Hash::make('CustomPassword123')]);

        $this->artisan('db:seed', ['--class' => UserSeeder::class, '--force' => true])->assertSuccessful();

        $this->assertTrue(Hash::check('CustomPassword123', $admin->fresh()->password));
    }

    public function test_user_seeder_is_skipped_in_production_without_flag(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('db:seed', ['--class' => UserSeeder::class, '--force' => true])->assertSuccessful();

        $this->assertSame(0, User::count());
    }
}
