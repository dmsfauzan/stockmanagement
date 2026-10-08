<?php

namespace App\Http\Middleware;

use App\Services\Security\SecurityMonitor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class RecordSecurityEvents
{
    /** @var array<int, string> */
    private const ASSET_EXTENSIONS = ['css', 'js', 'mjs', 'map', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico', 'woff', 'woff2', 'ttf', 'eot', 'csv', 'json'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! SecurityMonitor::enabled()) {
            return $next($request);
        }

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $this->capture($request, $this->statusFromException($e));

            throw $e;
        }

        $this->capture($request, $response->getStatusCode());

        return $response;
    }

    private function capture(Request $request, int $status): void
    {
        try {
            if ($request->attributes->get('security_banned') === true) {
                return;
            }

            $path = ltrim($request->path(), '/');

            if ($this->shouldSkip($path)) {
                return;
            }

            if (SecurityMonitor::isSuspiciousPath($path)) {
                SecurityMonitor::record('suspicious_path', 'high', $request, ['path' => $path, 'status' => $status]);
            }

            [$type, $severity] = match ($status) {
                401 => ['unauthenticated', 'warning'],
                403 => ['unauthorized', 'warning'],
                419 => ['csrf_mismatch', 'high'],
                429 => ['rate_limited', 'high'],
                default => [null, null],
            };

            if ($type !== null) {
                SecurityMonitor::record($type, $severity, $request, ['status' => $status, 'path' => $path]);
            }
        } catch (Throwable) {
        }
    }

    private function shouldSkip(string $path): bool
    {
        foreach ((array) config('security.skip_paths', []) as $prefix) {
            if ($prefix !== '' && str_starts_with($path, $prefix)) {
                return true;
            }
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $extension !== '' && in_array($extension, self::ASSET_EXTENSIONS, true);
    }

    private function statusFromException(Throwable $e): int
    {
        if ($e instanceof HttpExceptionInterface) {
            return $e->getStatusCode();
        }

        return 500;
    }
}
