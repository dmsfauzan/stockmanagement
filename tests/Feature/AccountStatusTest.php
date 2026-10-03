<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_user_cannot_login(): void
    {
        $this->seed();

        $user = User::where('email', 'admin@stock.test')->firstOrFail();
        $user->update(['status' => 'inactive']);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertInvalid(['email'])
            ->assertSessionHasErrors(['email']);

        $this->assertGuest();
    }

    public function test_last_login_at_updated_on_successful_login(): void
    {
        $this->seed();

        $user = User::where('email', 'admin@stock.test')->firstOrFail();
        $this->assertNull($user->last_login_at);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_inactive_user_is_logged_out_on_route_access(): void
    {
        $this->seed();

        $user = User::where('email', 'admin@stock.test')->firstOrFail();
        $this->actingAs($user);
        $user->update(['status' => 'inactive']);

        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }
}
