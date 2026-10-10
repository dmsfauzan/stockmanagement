<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
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
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string'],
        ]);

        $user = User::where('email', $request->string('email'))->first();

        if (! $user || ! Hash::check((string) $request->input('password'), (string) $user->password)) {
            return $this->error('Kredensial tidak valid.', 401);
        }

        if (! $user->isActive()) {
            return $this->error('Akun tidak aktif.', 403);
        }

        if ($user->hasTwoFactorEnabled()) {
            return $this->ok([
                'two_factor_required' => true,
                'user_id' => $user->id,
            ], 'Two-factor required.', 200);
        }

        $abilities = array_filter($request->input('abilities') ?? $user->permissionSlugs());
        $deviceName = trim((string) $request->input('device_name', 'mobile'));

        if ($deviceName === '') {
            $deviceName = 'mobile';
        }

        $token = $user->createToken($deviceName, $abilities);
        $user->forceFill(['last_login_at' => now()])->save();

        return $this->created([
            'token' => $token->plainTextToken,
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
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'code' => ['nullable', 'string', 'max:64'],
            'recovery_code' => ['nullable', 'string', 'max:64'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string'],
        ]);

        $user = User::findOrFail($request->integer('user_id'));

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

        $fresh = $user->fresh();
        $abilities = array_filter($request->input('abilities') ?? $fresh->permissionSlugs());
        $deviceName = trim((string) $request->input('device_name', 'mobile'));

        if ($deviceName === '') {
            $deviceName = 'mobile';
        }

        $token = $fresh->createToken($deviceName, $abilities);
        $fresh->forceFill(['last_login_at' => now()])->save();

        return $this->created([
            'token' => $token->plainTextToken,
            'user' => [
                'id' => $fresh->id,
                'name' => $fresh->name,
                'email' => $fresh->email,
                'roles' => $fresh->roleSlugs(),
                'permissions' => $fresh->permissionSlugs(),
            ],
        ], 'Login berhasil.');
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
