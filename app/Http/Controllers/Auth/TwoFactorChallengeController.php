<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Support\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('login.id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function store(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $request->validate([
            'code' => ['nullable', 'string', 'max:64'],
            'recovery_code' => ['nullable', 'string', 'max:64'],
        ]);

        $userId = $request->session()->get('login.id');
        $remember = (bool) $request->session()->get('login.remember', false);

        $user = $userId ? User::find($userId) : null;

        if (! $user || ! $user->hasTwoFactorEnabled()) {
            $request->session()->forget(['login.id', 'login.remember']);

            return redirect()->route('login');
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
            throw ValidationException::withMessages([
                'code' => 'Kode verifikasi tidak valid.',
            ]);
        }

        Auth::loginUsingId($user->id, $remember);

        $request->session()->forget(['login.id', 'login.remember']);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
