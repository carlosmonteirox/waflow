<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $request->attributes->get('waflow.workspace');
        if (! $workspace || $workspace->status !== 'active' || $workspace->owner?->blocked_at) {
            return response()->json(['message' => 'Workspace indisponível.'], 403);
        }

        if (! $workspace->owner?->hasVerifiedEmail()) {
            return response()->json(['message' => 'Confirme o e-mail da conta antes de usar a API.', 'code' => 'email_not_verified'], 403);
        }

        $subscription = $workspace->subscription;
        if (! $subscription || ! in_array($subscription->status, ['trialing', 'active'], true)) {
            return response()->json(['message' => 'Assinatura inativa.', 'code' => 'subscription_inactive'], 402);
        }

        if ($subscription->status === 'trialing' && $subscription->trial_ends_at?->isPast()) {
            $subscription->update(['status' => 'expired']);

            return response()->json(['message' => 'O período de avaliação terminou.', 'code' => 'trial_expired'], 402);
        }

        return $next($request);
    }
}
