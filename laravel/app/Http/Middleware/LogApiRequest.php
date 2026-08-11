<?php

namespace App\Http\Middleware;

use App\Models\ApiRequestLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $started = microtime(true);
        $requestId = (string) Str::uuid();
        $request->headers->set('X-Request-Id', $requestId);
        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        $key = $request->attributes->get('waflow.api_key');
        try {
            ApiRequestLog::query()->create([
                'workspace_id' => $key?->workspace_id,
                'api_key_id' => $key?->id,
                'request_id' => $requestId,
                'method' => $request->method(),
                'path' => '/'.$request->path(),
                'status_code' => $response->getStatusCode(),
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'ip_hash' => $request->ip() ? hash_hmac('sha256', $request->ip(), (string) config('app.key')) : null,
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $response;
    }
}
