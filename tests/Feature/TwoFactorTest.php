<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Support\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function enableTwoFactorFor(User $user): string
    {
        $google = new Google2FA;
        $secret = $google->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => TwoFactorService::hashRecoveryCodes(['RC1']),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $secret;
    }

    public function test_login_triggers_two_factor_challenge(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();
        $this->enableTwoFactorFor($admin);

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.challenge'));

        $this->assertGuest();
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_two_factor_challenge_accepts_valid_code(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();
        $secret = $this->enableTwoFactorFor($admin);

        $this->post('/login', ['email' => $admin->email, 'password' => 'password']);

        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $this->post(route('two-factor.verify'), ['code' => $code])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_two_factor_recovery_code_can_login(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();
        $secret = $this->enableTwoFactorFor($admin);
        $admin->forceFill(['two_factor_recovery_codes' => TwoFactorService::hashRecoveryCodes(['RCX1234567'])])->save();

        $this->post('/login', ['email' => $admin->email, 'password' => 'password']);

        $this->post(route('two-factor.verify'), ['code' => '', 'recovery_code' => 'RCX1234567'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);
        $this->assertSame([], (array) $admin->fresh()->two_factor_recovery_codes);
    }

    public function test_recovery_codes_are_stored_hashed(): void
    {
        $user = User::where('email', 'admin@stock.test')->firstOrFail();

        $plain = TwoFactorService::generateRecoveryCodes(2);
        $user->forceFill(['two_factor_recovery_codes' => TwoFactorService::hashRecoveryCodes($plain)])->save();

        $stored = (array) $user->fresh()->two_factor_recovery_codes;

        $this->assertCount(2, $stored);
        foreach ($plain as $code) {
            $this->assertNotContains($code, $stored, 'Recovery code stored in plain text.');
        }
        $this->assertTrue(Hash::check($plain[0], $stored[0]));
    }

    public function test_invalid_two_factor_code_is_rejected(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();
        $this->enableTwoFactorFor($admin);

        $this->post('/login', ['email' => $admin->email, 'password' => 'password']);

        $this->post(route('two-factor.verify'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_user_without_two_factor_logs_in_directly(): void
    {
        $staff = User::where('email', 'staff@stock.test')->firstOrFail();

        $this->post('/login', ['email' => $staff->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($staff);
    }
}
