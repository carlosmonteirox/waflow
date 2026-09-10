<?php

namespace App\Http\Middleware;

use App\Services\PartnerContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePartnerMember
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app(PartnerContext::class)->forUser($request->user()), 403, 'Sua conta não está vinculada a um parceiro ativo.');

        return $next($request);
    }
}
