<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_public_health_endpoint_returns_ok(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_detailed_health_requires_admin(): void
    {
        $staff = User::where('email', 'staff@stock.test')->firstOrFail();
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        $this->actingAs($staff)->getJson(route('admin.health'))->assertStatus(403);

        $this->actingAs($admin)->getJson(route('admin.health'))
            ->assertOk()
            ->assertJsonPath('data.checks.database.ok', true)
            ->assertJsonPath('data.checks.storage.ok', true);
    }

    public function test_security_headers_are_present(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_weak_password_is_rejected_on_reset(): void
    {
        $this->post('/reset-password', [
            'token' => 'invalid',
            'email' => 'nobody@example.com',
            'password' => 'abcdefgh',
            'password_confirmation' => 'abcdefgh',
        ])->assertSessionHasErrors('password');
    }
}
