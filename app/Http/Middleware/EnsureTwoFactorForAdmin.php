<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorForAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $required = (string) (Setting::get('security.require_2fa_admin', '0') ?? '0');

        if ($required !== '1') {
            return $next($request);
        }

        if (! method_exists($user, 'hasRole') || ! $user->hasRole('admin')) {
            return $next($request);
        }

        if (method_exists($user, 'hasTwoFactorEnabled') && $user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        // Allow access to the setup page, profile, logout and notifications.
        if ($request->routeIs('profile.*') || $request->routeIs('logout') || $request->routeIs('notifications.*')) {
            return $next($request);
        }

        return redirect()->route('profile.two-factor')
            ->with('warning', 'Aktifkan two-factor authentication sebelum melanjutkan.');
    }
}
