<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && method_exists($user, 'isActive') && ! $user->isActive()) {
            if ($request->is('api/*') || $request->expectsJson()) {
                if ($user->currentAccessToken() && method_exists($user->currentAccessToken(), 'delete')) {
                    $user->currentAccessToken()->delete();
                }

                abort(403, __('Akun tidak aktif.'));
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Akun tidak aktif.']);
        }

        return $next($request);
    }
}
