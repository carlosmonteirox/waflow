<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    use ResolvesWorkspace;

    public function usage(Request $request): JsonResponse
    {
        $workspace = $this->workspace($request)->load('subscription.plan');
        $plan = $workspace->subscription?->plan;
        $monthStart = now()->startOfMonth();

        return response()->json([
            'data' => [
                'subscription' => [
                    'status' => $workspace->subscription?->status,
                    'billing_cycle' => $workspace->subscription?->billing_cycle,
                    'trial_ends_at' => $workspace->subscription?->trial_ends_at?->toIso8601String(),
                    'current_period_ends_at' => $workspace->subscription?->current_period_ends_at?->toIso8601String(),
                ],
                'plan' => $plan?->only(['name', 'slug', 'monthly_price_cents']),
                'usage' => [
                    'instances' => [
                        'used' => $workspace->instances()->count(),
                        'limit' => $plan?->instance_limit,
                    ],
                    'messages' => [
                        'used' => $workspace->messageEvents()->where('created_at', '>=', $monthStart)->count(),
                        'limit' => $plan?->message_limit,
                    ],
                    'webhooks' => [
                        'used' => $workspace->webhooks()->count(),
                        'limit' => $plan?->webhook_limit,
                    ],
                    'api_keys' => [
                        'used' => $workspace->apiKeys()->count(),
                        'limit' => $plan?->api_key_limit,
                    ],
                ],
            ],
        ]);
    }
}
