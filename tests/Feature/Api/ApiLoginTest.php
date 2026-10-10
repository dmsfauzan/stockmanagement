<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\Support\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_login_returns_token_usable_for_me(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'admin@stock.test',
            'password' => 'password',
            'device_name' => 'Pixel-7',
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'admin@stock.test');

        $token = $response->json('data.token');
        $this->assertIsString($token);

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'admin@stock.test');
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $this->postJson('/api/login', [
            'email' => 'admin@stock.test',
            'password' => 'salah',
        ])->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_login_rejects_inactive_account(): void
    {
        User::where('email', 'admin@stock.test')->update(['status' => 'inactive']);

        $this->postJson('/api/login', [
            'email' => 'admin@stock.test',
            'password' => 'password',
        ])->assertStatus(403);
    }

    public function test_two_factor_login_flow_with_recovery_code(): void
    {
        $user = User::where('email', 'admin@stock.test')->firstOrFail();
        $user->forceFill([
            'two_factor_secret' => app(TwoFactorService::class)->generateSecret(),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => TwoFactorService::hashRecoveryCodes(['ABCDEFGHIJ']),
        ])->save();

        $this->postJson('/api/login', [
            'email' => 'admin@stock.test',
            'password' => 'password',
        ])->assertOk()->assertJsonPath('data.two_factor_required', true);

        $this->postJson('/api/two-factor-challenge', [
            'user_id' => $user->id,
            'recovery_code' => 'ABCDEFGHIJ',
            'device_name' => 'Pixel-7',
        ])->assertCreated()->assertJsonPath('data.user.id', $user->id);

        $this->postJson('/api/two-factor-challenge', [
            'user_id' => $user->id,
            'recovery_code' => 'SALAH',
        ])->assertStatus(401);
    }

    public function test_logout_revokes_current_token(): void
    {
        $token = $this->postJson('/api/login', [
            'email' => 'admin@stock.test',
            'password' => 'password',
        ])->json('data.token');

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/me')->assertStatus(401);
    }
}
