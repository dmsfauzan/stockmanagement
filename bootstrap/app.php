<?php

use App\Http\Middleware\BlockBannedIps;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureIdempotency;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureTwoFactorForAdmin;
use App\Http\Middleware\EnsureWarehouseAccess;
use App\Http\Middleware\RecordSecurityEvents;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => EnsurePermission::class,
            'active' => EnsureAccountActive::class,
            'idempotent' => EnsureIdempotency::class,
            'twofactor.admin' => EnsureTwoFactorForAdmin::class,
            'warehouse.scope' => EnsureWarehouseAccess::class,
        ]);
        $middleware->throttleApi();
        $middleware->prepend(BlockBannedIps::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->append(RecordSecurityEvents::class);
        $middleware->web(append: [SetLocale::class, EnsureWarehouseAccess::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })
    ->create();

return $app;
