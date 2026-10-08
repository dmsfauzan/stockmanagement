<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            UserSeeder::class,
            MasterDataSeeder::class,
        ]);

        if (app()->isProduction() && ! filter_var(env('SEED_DEMO', false), FILTER_VALIDATE_BOOL)) {
            $this->command?->warn('DemoDataSeeder dilewati di produksi (set SEED_DEMO=true untuk memaksa).');

            return;
        }

        $this->call([DemoDataSeeder::class]);
    }
}
