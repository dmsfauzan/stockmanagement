<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthRouteThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_sensitive_auth_routes_are_throttled(): void
    {
        $names = ['login', 'two-factor.verify', 'password.email', 'password.store'];

        foreach ($names as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "Route {$name} missing.");
            $this->assertContains('throttle:6,1', $route->gatherMiddleware(), "Route {$name} not throttled.");
        }
    }

    public function test_confirm_password_route_is_throttled(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => in_array('POST', $r->methods(), true) && $r->uri() === 'confirm-password');

        $this->assertNotNull($route);
        $this->assertContains('throttle:6,1', $route->gatherMiddleware());
    }
}
