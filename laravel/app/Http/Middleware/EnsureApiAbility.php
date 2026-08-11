<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $abilities = $request->attributes->get('waflow.api_key')?->abilities ?? [];
        $prefix = str($ability)->before(':').':*';
        if (! in_array('*', $abilities, true) && ! in_array($ability, $abilities, true) && ! in_array($prefix, $abilities, true)) {
            return response()->json(['message' => 'A chave não possui permissão para esta operação.'], 403);
        }

        return $next($request);
    }
}
