<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainTextToken = $request->bearerToken() ?: $request->header('X-Waflow-Key');

        if (! is_string($plainTextToken) || $plainTextToken === '') {
            return ApiResponse::error($request, 'api_key_missing', 'Chave de API ausente.', 401);
        }

        $apiKey = ApiKey::query()
            ->with(['workspace.subscription.plan', 'workspace.owner'])
            ->where('token_hash', hash('sha256', $plainTextToken))
            ->first();

        if (! $apiKey || ($apiKey->expires_at && $apiKey->expires_at->isPast())) {
            return ApiResponse::error($request, 'api_key_invalid', 'Chave de API inválida.', 401);
        }

        $apiKey->forceFill(['last_used_at' => now()])->save();

        $request->attributes->set('waflow.api_key', $apiKey);
        $request->attributes->set('waflow.workspace', $apiKey->workspace);

        return $next($request);
    }
}
