<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LimitRequestBodySize
{
    public function handle(Request $request, Closure $next, int|string|null $maxBytes = null): Response
    {
        $limit = max(1, (int) ($maxBytes ?? config('waflow.webhooks.max_payload_bytes')));
        $declaredLength = (int) $request->server('CONTENT_LENGTH', 0);

        if ($declaredLength > $limit || strlen($request->getContent()) > $limit) {
            return ApiResponse::error($request, 'payload_too_large', 'O corpo da solicitação excede o limite permitido.', 413);
        }

        return $next($request);
    }
}
