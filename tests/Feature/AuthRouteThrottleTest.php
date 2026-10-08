<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthRouteThrottleTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, array{0: string, 1: string}> */
    private function throttledPostRoutes(): array
    {
        return [
            ['login', 'POST'],
            ['two-factor-challenge', 'POST'],
            ['forgot-password', 'POST'],
            ['reset-password', 'POST'],
            ['confirm-password', 'POST'],
        ];
    }

    public function test_sensitive_auth_routes_are_throttled(): void
    {
        foreach ($this->throttledPostRoutes() as [$uri, $method]) {
            $route = collect(Route::getRoutes()->getRoutes())
                ->first(fn ($r) => in_array($method, $r->methods(), true) && $r->uri() === $uri);

            $this->assertNotNull($route, "Route {$method} {$uri} missing.");
            $this->assertContains('throttle:6,1', $route->gatherMiddleware(), "Route {$method} {$uri} not throttled.");
        }
    }
}
