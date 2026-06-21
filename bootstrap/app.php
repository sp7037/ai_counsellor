<?php

use App\Exceptions\Widget\WidgetGatewayDeniedException;
use App\Http\Middleware\ClearTenantContext;
use App\Http\Middleware\EnsureCounsellorSubscription;
use App\Http\Middleware\EnsureCounsellorWorkspace;
use App\Http\Middleware\EnsureFeatureEntitled;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureTenantBillingManager;
use App\Http\Middleware\EnsureTenantIntegrationManager;
use App\Http\Middleware\EnsureTenantLeadManager;
use App\Http\Middleware\EnsureTenantOperational;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ResolveTenant;
use App\Http\Support\WidgetCorsResponse;
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
            'tenant.operational' => EnsureTenantOperational::class,
            'tenant.feature' => EnsureFeatureEntitled::class,
            'tenant.lead.manager' => EnsureTenantLeadManager::class,
            'tenant.billing' => EnsureTenantBillingManager::class,
            'tenant.integrations' => EnsureTenantIntegrationManager::class,
            'counsellor.workspace' => EnsureCounsellorWorkspace::class,
            'counsellor.subscription' => EnsureCounsellorSubscription::class,
            'user.active' => EnsureUserIsActive::class,
        ]);

        $middleware->prependToGroup('web', ClearTenantContext::class);
        $middleware->appendToGroup('web', EnsureUserIsActive::class);
        $middleware->prependToGroup('api', ClearTenantContext::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('widget/*'),
        );

        $exceptions->render(function (WidgetGatewayDeniedException $exception, Request $request) {
            if (! $request->is('widget/*')) {
                return null;
            }

            return app(WidgetCorsResponse::class)->json($request, [
                'message' => $exception->getMessage(),
                'code' => $exception->errorCode,
            ], 403, ['Cache-Control' => 'no-store, private']);
        });
    })->create();
