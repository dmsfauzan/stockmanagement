<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Support\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Throwable;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        try {
            NotificationService::notify(
                (int) $request->user()->getKey(),
                'security.password',
                'Password diubah',
                'Password akun Anda baru saja diubah. Jika bukan Anda, hubungi admin segera.',
                User::class,
                (int) $request->user()->getKey(),
            );
        } catch (Throwable) {
        }

        return back()->with('status', 'password-updated');
    }
}
