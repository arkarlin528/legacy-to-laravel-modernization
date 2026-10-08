<?php

use App\Exceptions\BusinessRuleException;
use App\Http\Legacy\LegacyApi;
use App\Http\Middleware\LegacyApiKey;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['legacy.key' => LegacyApiKey::class]);
        // Behind the strangler facade (nginx) and Caddy.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // v1 routes answer errors in the legacy Web API 2 format; v2 keeps Laravel's standard JSON.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (LegacyApi::handles($request)) {
                return LegacyApi::renderException($e);
            }
            if ($e instanceof BusinessRuleException) {
                return response()->json(['message' => $e->getMessage()], $e->status);
            }

            return null;
        });

        $exceptions->dontReport(BusinessRuleException::class);
    })->create();
