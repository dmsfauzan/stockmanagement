<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Administrator', 'email' => 'admin@stock.test', 'role' => 'admin'],
            ['name' => 'Warehouse Staff', 'email' => 'staff@stock.test', 'role' => 'warehouse_staff'],
            ['name' => 'Supervisor', 'email' => 'supervisor@stock.test', 'role' => 'supervisor'],
            ['name' => 'Manager', 'email' => 'manager@stock.test', 'role' => 'manager'],
        ];

        foreach ($users as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                ['name' => $data['name'], 'password' => 'password', 'status' => 'active']
            );

            $role = Role::where('slug', $data['role'])->first();

            if ($role) {
                \Illuminate\Support\Facades\DB::table('user_role')->updateOrInsert(
                    ['user_id' => $user->id, 'role_id' => $role->id],
                    []
                );
            }
        }
    }
}
