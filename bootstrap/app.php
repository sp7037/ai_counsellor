<?php

use App\Http\Middleware\ClearTenantContext;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('api')
                ->prefix('widget/v1')
                ->group(base_path('routes/widget.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'platform.admin' => EnsurePlatformAdmin::class,
            'tenant.resolve' => ResolveTenant::class,
            'user.active' => EnsureUserIsActive::class,
        ]);

        $middleware->prependToGroup('web', ClearTenantContext::class);
        $middleware->appendToGroup('web', EnsureUserIsActive::class);
        $middleware->prependToGroup('api', ClearTenantContext::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
