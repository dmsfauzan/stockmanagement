<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_manager_cannot_access_users_page(): void
    {
        $manager = User::where('email', 'manager@stock.test')->firstOrFail();
        $this->actingAs($manager)->get(route('admin.users'))->assertStatus(403);
    }

    public function test_staff_cannot_access_roles_or_settings_or_reports(): void
    {
        $staff = User::where('email', 'staff@stock.test')->firstOrFail();
        $this->actingAs($staff)->get(route('admin.roles'))->assertStatus(403);
        $this->actingAs($staff)->get(route('admin.settings'))->assertStatus(403);
        $this->actingAs($staff)->get(route('reports.stock'))->assertStatus(403);
    }

    public function test_supervisor_can_view_reports_and_incoming_outgoing(): void
    {
        $sup = User::where('email', 'supervisor@stock.test')->firstOrFail();
        $this->actingAs($sup)->get(route('reports.stock'))->assertStatus(200);
        $this->actingAs($sup)->get(route('reports.movement'))->assertStatus(200);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertStatus(302);
        $this->get(route('items.index'))->assertStatus(302);
    }

    public function test_admin_bypasses_every_permission(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();
        foreach (['admin.users', 'admin.roles', 'admin.settings', 'reports.stock'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertStatus(200);
        }
    }
}
