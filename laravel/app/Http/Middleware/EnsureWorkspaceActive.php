<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $request->attributes->get('waflow.workspace');
        if (! $workspace || $workspace->status !== 'active' || $workspace->owner?->blocked_at) {
            return ApiResponse::error($request, 'workspace_unavailable', 'Workspace indisponível.', 403);
        }

        if (! $workspace->owner?->hasVerifiedEmail()) {
            return ApiResponse::error($request, 'email_not_verified', 'Confirme o e-mail da conta antes de usar a API.', 403);
        }

        $subscription = $workspace->subscription;
        if (! $subscription || ! in_array($subscription->status, ['trialing', 'active'], true)) {
            return ApiResponse::error($request, 'subscription_inactive', 'Assinatura inativa.', 402);
        }

        if ($subscription->status === 'trialing' && $subscription->trial_ends_at?->isPast()) {
            $subscription->update(['status' => 'expired']);

            return ApiResponse::error($request, 'trial_expired', 'O período de avaliação terminou.', 402);
        }

        return $next($request);
    }
}
