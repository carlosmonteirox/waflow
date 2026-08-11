<?php

use App\Exceptions\InterApiException;
use App\Exceptions\OpenWaException;
use App\Http\Middleware\AttachRequestId;
use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\EnsureApiAbility;
use App\Http\Middleware\EnsureWorkspaceActive;
use App\Http\Middleware\LogApiRequest;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
        $middleware->api(append: [AttachRequestId::class]);
        $middleware->alias([
            'waflow.api' => AuthenticateApiKey::class,
            'waflow.subscription' => EnsureWorkspaceActive::class,
            'waflow.ability' => EnsureApiAbility::class,
            'waflow.audit' => LogApiRequest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (InterApiException $exception, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return ApiResponse::error($request, $exception->providerCode ?? 'billing_provider_error', $exception->getMessage(), $exception->status);
            }
        });
        $exceptions->render(function (OpenWaException $exception, Request $request) {
            if ($request->expectsJson()) {
                return ApiResponse::error($request, 'whatsapp_runtime_error', $exception->getMessage(), $exception->status >= 400 && $exception->status < 500 ? $exception->status : 502);
            }
        });
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($request, 'validation_failed', 'Os dados enviados são inválidos.', 422, $exception->errors());
            }
        });
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($request, 'unauthenticated', 'Autenticação necessária.', 401);
            }
        });
        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($request, 'forbidden', $exception->getMessage() ?: 'Operação não autorizada.', 403);
            }
        });
        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($request, 'resource_not_found', 'Recurso não encontrado.', 404);
            }
        });
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if ($request->is('api/*')) {
                $status = $exception->getStatusCode();
                $code = match ($status) {
                    401 => 'unauthenticated', 402 => 'subscription_required', 403 => 'forbidden',
                    404 => 'resource_not_found', 409 => 'conflict', 410 => 'resource_expired',
                    429 => 'rate_limit_exceeded', default => 'http_error',
                };

                return ApiResponse::error($request, $code, $exception->getMessage() ?: 'Não foi possível concluir a solicitação.', $status);
            }
        });
    })->create();
