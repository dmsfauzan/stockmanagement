<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function __construct(private ?string $permission = null) {}

    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        $required = $permission ?? $this->permission;

        if ($required === null || $required === '') {
            abort(403, 'Permission required.');
        }

        $user = $request->user();

        if (! $user || ! $user->hasPermission($required)) {
            abort(403, 'Unauthorized. Missing permission: ' . $required);
        }

        return $next($request);
    }
}
