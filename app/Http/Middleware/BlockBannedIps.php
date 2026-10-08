<?php

namespace App\Http\Middleware;

use App\Services\Security\SecurityMonitor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class BlockBannedIps
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $ip = $request->ip();

            if (is_string($ip) && $ip !== '' && SecurityMonitor::enabled() && SecurityMonitor::isBanned($ip) !== null) {
                $request->attributes->set('security_banned', true);

                try {
                    SecurityMonitor::record('banned_blocked', 'high', $request, ['blocked' => true]);
                } catch (Throwable) {
                }

                abort(403, 'Access denied.');
            }
        } catch (Throwable $e) {
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }
        }

        return $next($request);
    }
}
