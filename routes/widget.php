<?php

use App\Http\Controllers\Widget\WidgetGatewayController;
use App\Http\Middleware\HandleWidgetCors;
use App\Http\Middleware\ResolveWidgetSession;
use Illuminate\Support\Facades\Route;

Route::middleware([HandleWidgetCors::class, 'throttle:'.config('widget.rate_limit.session_start', '20,1')])
    ->post('session', [WidgetGatewayController::class, 'startSession']);

Route::middleware([HandleWidgetCors::class, ResolveWidgetSession::class])
    ->group(function (): void {
        Route::middleware('throttle:'.config('ai.rate_limit.messages', config('widget.rate_limit.messages', '60,1')))->group(function (): void {
            Route::get('config', [WidgetGatewayController::class, 'config']);
            Route::get('knowledge/search', [WidgetGatewayController::class, 'searchKnowledge']);
            Route::post('messages', [WidgetGatewayController::class, 'sendMessage']);
            Route::post('offline', [WidgetGatewayController::class, 'submitOffline']);
        });
    });
