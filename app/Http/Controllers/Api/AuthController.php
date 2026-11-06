<?php

namespace App\Http\Controllers\Api;

use App\Models\Setting;
use App\Models\User;
use App\Services\Support\ApiTokenService;
use App\Services\Support\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends ApiController
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::where('email', $request->string('email'))->first();

        if (! $user || ! Hash::check((string) $request->input('password'), (string) $user->password)) {
            return $this->error('Kredensial tidak valid.', 401);
        }

        if (! $user->isActive()) {
            return $this->error('Akun tidak aktif.', 403);
        }

        if ($this->requiresTwoFactorSetup($user)) {
            return $this->error('Aktifkan two-factor authentication sebelum menggunakan API.', 403);
        }

        if ($user->hasTwoFactorEnabled()) {
            return $this->ok([
                'two_factor_required' => true,
                'challenge_token' => ApiTokenService::issueTwoFactorChallenge((int) $user->id),
            ], 'Two-factor required.', 200);
        }

        $abilities = array_values(array_filter($user->permissionSlugs()));
        $deviceName = trim((string) $request->input('device_name', 'mobile'));

        if ($deviceName === '') {
            $deviceName = 'mobile';
        }

        $token = $user->createToken($deviceName, $abilities);
        $user->forceFill(['last_login_at' => now()])->save();

        return $this->created([
            'token' => ApiTokenService::plainText($token),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roleSlugs(),
                'permissions' => $user->permissionSlugs(),
            ],
        ], 'Login berhasil.');
    }

    public function twoFactorChallenge(Request $request, TwoFactorService $twoFactor): JsonResponse
    {
        $request->validate([
            'challenge_token' => ['required', 'string', 'max:128'],
            'code' => ['nullable', 'string', 'max:64'],
            'recovery_code' => ['nullable', 'string', 'max:64'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $challengeToken = (string) $request->input('challenge_token');
        $challenge = ApiTokenService::resolveTwoFactorChallenge($challengeToken);

        if ($challenge === null) {
            return $this->error('Sesi verifikasi 2FA tidak valid atau kedaluwarsa. Silakan login ulang.', 419);
        }

        if (ApiTokenService::registerTwoFactorAttempt($challengeToken) > ApiTokenService::maxTwoFactorAttempts()) {
            ApiTokenService::forgetTwoFactorChallenge($challengeToken);

            return $this->error('Terlalu banyak percobaan verifikasi. Silakan login ulang.', 429);
        }

        $user = User::find($challenge['user_id']);

        if (! $user) {
            ApiTokenService::forgetTwoFactorChallenge($challengeToken);

            return $this->error('Sesi verifikasi 2FA tidak valid. Silakan login ulang.', 419);
        }

        if (! $user->isActive()) {
            return $this->error('Akun tidak aktif.', 403);
        }

        if (! $user->hasTwoFactorEnabled()) {
            return $this->error('Two-factor belum aktif.', 422);
        }

        $code = (string) $request->input('code', '');
        $recovery = (string) $request->input('recovery_code', '');
        $valid = false;

        if ($code !== '' && $twoFactor->verify((string) $user->two_factor_secret, $code)) {
            $valid = true;
        } elseif ($recovery !== '' && TwoFactorService::consumeRecoveryCode($user, $recovery)) {
            $valid = true;
        }

        if (! $valid) {
            return $this->error('Kode verifikasi tidak valid.', 401);
        }

        ApiTokenService::forgetTwoFactorChallenge($challengeToken);

        $fresh = $user->fresh();
        $abilities = array_values(array_filter($fresh->permissionSlugs()));
        $deviceName = trim((string) $request->input('device_name', 'mobile'));

        if ($deviceName === '') {
            $deviceName = 'mobile';
        }

        $token = $fresh->createToken($deviceName, $abilities);
        $fresh->forceFill(['last_login_at' => now()])->save();

        return $this->created([
            'token' => ApiTokenService::plainText($token),
            'user' => [
                'id' => $fresh->id,
                'name' => $fresh->name,
                'email' => $fresh->email,
                'roles' => $fresh->roleSlugs(),
                'permissions' => $fresh->permissionSlugs(),
            ],
        ], 'Login berhasil.');
    }

    /**
     * When the "require 2FA for admin" setting is on, an admin without 2FA
     * must not be able to mint an API token with a password alone.
     */
    private function requiresTwoFactorSetup(User $user): bool
    {
        if ($user->hasTwoFactorEnabled() || ! $user->hasRole('admin')) {
            return false;
        }

        return (string) (Setting::get('security.require_2fa_admin', '0') ?? '0') === '1';
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        return $this->ok(null, 'Logout berhasil.');
    }
}
