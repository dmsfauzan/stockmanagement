<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SessionAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_login_and_logout_are_audited(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        $this->post('/login', ['email' => $admin->email, 'password' => 'password']);
        $this->assertAuthenticatedAs($admin);
        $this->assertDatabaseHas('audit_logs', ['action' => 'LOGIN', 'module' => 'auth']);

        $this->post(route('logout'));
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'LOGOUT', 'module' => 'auth']);
    }

    public function test_sessions_page_and_logout_others(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('profile.sessions'))
            ->assertOk();

        Livewire::actingAs($admin)->test('profile.sessions-index')
            ->call('logoutOthers')
            ->assertDispatched('toast');

        $this->assertDatabaseHas('audit_logs', ['action' => 'LOGOUT_OTHERS', 'module' => 'session']);
    }
}
