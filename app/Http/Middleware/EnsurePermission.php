<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
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
            return $this->deny($request, 'Unauthorized. Missing permission: '.$required);
        }

        // When authenticated via a Sanctum token, enforce the token's abilities
        // so a token scoped to specific permissions cannot exceed them.
        $token = method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;

        if ($token instanceof PersonalAccessToken) {
            $abilities = (array) ($token->abilities ?? []);

            // A token with no explicit abilities inherits the user's full access
            // (legacy behaviour). Only enforce when abilities are defined.
            if ($abilities !== [] && ! $token->can('*') && ! $token->can($required)) {
                return $this->deny($request, 'Unauthorized. Token lacks ability: '.$required);
            }
        }

        return $next($request);
    }

    private function deny(Request $request, string $message): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 403);
        }

        abort(403, $message);
    }
}
