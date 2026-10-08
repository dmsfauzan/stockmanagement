<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_login_creates_security_notification(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'security.login',
        ]);
    }

    public function test_password_change_creates_security_notification(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        $this->actingAs($admin)->put('/password', [
            'current_password' => 'password',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'security.password',
        ]);
    }
}
