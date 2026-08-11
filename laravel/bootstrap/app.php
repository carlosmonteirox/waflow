<?php

use App\Exceptions\OpenWaException;
use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\EnsureApiAbility;
use App\Http\Middleware\EnsureWorkspaceActive;
use App\Http\Middleware\LogApiRequest;
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
        // The PHP application is only reachable through the internal Nginx service.
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'waflow.api' => AuthenticateApiKey::class,
            'waflow.subscription' => EnsureWorkspaceActive::class,
            'waflow.ability' => EnsureApiAbility::class,
            'waflow.audit' => LogApiRequest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (OpenWaException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'code' => 'whatsapp_runtime_error',
                ], $exception->status >= 400 && $exception->status < 500 ? $exception->status : 502);
            }
        });
    })->create();
