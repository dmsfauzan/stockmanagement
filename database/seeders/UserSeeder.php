<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction() && ! filter_var(env('SEED_DEMO_USERS', false), FILTER_VALIDATE_BOOL)) {
            $this->command?->warn('UserSeeder dilewati di produksi (set SEED_DEMO_USERS=true untuk memaksa).');

            return;
        }

        $users = [
            ['name' => 'Administrator', 'email' => 'admin@stock.test', 'role' => 'admin'],
            ['name' => 'Warehouse Staff', 'email' => 'staff@stock.test', 'role' => 'warehouse_staff'],
            ['name' => 'Supervisor', 'email' => 'supervisor@stock.test', 'role' => 'supervisor'],
            ['name' => 'Manager', 'email' => 'manager@stock.test', 'role' => 'manager'],
        ];

        foreach ($users as $data) {
            $user = User::firstOrNew(['email' => $data['email']]);
            $user->name = $data['name'];
            $user->status = 'active';

            // Never overwrite an existing user's password on re-seed.
            if (! $user->exists) {
                $user->password = 'password';
            }

            $user->save();

            $role = Role::where('slug', $data['role'])->first();

            if ($role) {
                DB::table('user_role')->updateOrInsert(
                    ['user_id' => $user->id, 'role_id' => $role->id],
                    []
                );
            }
        }
    }
}
