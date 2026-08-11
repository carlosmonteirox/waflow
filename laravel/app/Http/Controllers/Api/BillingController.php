<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\BillingCheckoutSession;
use App\Models\Invoice;
use App\Models\Plan;
use App\Services\BillingService;
use App\Services\UsageLimiter;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BillingController extends Controller
{
    use ResolvesWorkspace;

    public function usage(Request $request, UsageLimiter $limiter): JsonResponse
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
                        'limit' => $limiter->limit($workspace, 'instances'),
                    ],
                    'messages' => [
                        'used' => $workspace->messageEvents()->where('direction', 'outbound')->where('created_at', '>=', $monthStart)->count(),
                        'limit' => $limiter->limit($workspace, 'messages'),
                    ],
                    'webhooks' => [
                        'used' => $workspace->webhooks()->count(),
                        'limit' => $limiter->limit($workspace, 'webhooks'),
                    ],
                    'api_keys' => [
                        'used' => $workspace->apiKeys()->count(),
                        'limit' => $limiter->limit($workspace, 'api_keys'),
                    ],
                    'portals' => [
                        'used' => $workspace->portals()->count(),
                        'limit' => $limiter->limit($workspace, 'portals'),
                    ],
                ],
            ],
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $workspace = $this->workspace($request)->load('subscription.plan');

        return response()->json(['data' => [
            'subscription' => $workspace->subscription,
            'plan' => $workspace->subscription?->plan,
            'payment_methods' => config('billing.payment_methods'),
            'provider_status' => config('billing.driver') === 'pending' ? 'pending_selection' : 'configured',
            'latest_checkout' => $workspace->checkoutSessions()->with('plan')->latest()->first(),
            'latest_invoice' => $workspace->invoices()->latest()->first(),
        ]]);
    }

    public function checkouts(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'status' => ['nullable', Rule::in(['draft', 'provider_pending', 'pending', 'completed', 'paid', 'cancelled', 'expired', 'failed'])],
            'payment_method' => ['nullable', Rule::in(config('billing.payment_methods'))],
            'plan_slug' => ['nullable', 'string', 'max:80'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = $this->workspace($request)->checkoutSessions()->with('plan')->latest();
        $query->when(filled($attributes['status'] ?? null), fn ($query) => $query->where('status', $attributes['status']));
        $query->when(filled($attributes['payment_method'] ?? null), fn ($query) => $query->where('payment_method', $attributes['payment_method']));
        $query->when(filled($attributes['plan_slug'] ?? null), fn ($query) => $query->whereHas('plan', fn ($plans) => $plans->where('slug', $attributes['plan_slug'])));

        return ApiResponse::paginated($query->paginate(ApiResponse::perPage($request)));
    }

    public function createCheckout(Request $request, BillingService $billing): JsonResponse
    {
        $attributes = $request->validate([
            'plan_slug' => ['required', 'string', Rule::exists('plans', 'slug')],
            'payment_method' => ['required', Rule::in(config('billing.payment_methods'))],
        ]);
        $checkout = $billing->createCheckout(
            $this->workspace($request),
            $request->attributes->get('waflow.workspace')->owner,
            Plan::query()->where('slug', $attributes['plan_slug'])->firstOrFail(),
            $attributes['payment_method'],
        );

        return response()->json(['data' => $checkout], $checkout->status === 'provider_pending' ? 202 : 201);
    }

    public function showCheckout(Request $request, BillingCheckoutSession $checkout): JsonResponse
    {
        $this->ensureWorkspace($checkout, $this->workspace($request));

        return response()->json(['data' => $checkout->load('plan')]);
    }

    public function cancelCheckout(Request $request, BillingCheckoutSession $checkout): JsonResponse
    {
        $this->ensureWorkspace($checkout, $this->workspace($request));
        abort_if(in_array($checkout->status, ['completed', 'paid'], true), 409, 'Um checkout concluído não pode ser cancelado.');
        $checkout->update(['status' => 'cancelled']);

        return response()->json(['data' => $checkout->fresh()]);
    }

    public function invoices(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'status' => ['nullable', Rule::in(['draft', 'open', 'pending', 'paid', 'void', 'overdue', 'failed'])],
            'payment_method' => ['nullable', Rule::in(config('billing.payment_methods'))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = $this->workspace($request)->invoices()->latest();
        $query->when(filled($attributes['status'] ?? null), fn ($query) => $query->where('status', $attributes['status']));
        $query->when(filled($attributes['payment_method'] ?? null), fn ($query) => $query->where('payment_method', $attributes['payment_method']));

        return ApiResponse::paginated($query->paginate(ApiResponse::perPage($request)));
    }

    public function showInvoice(Request $request, Invoice $invoice): JsonResponse
    {
        $this->ensureWorkspace($invoice, $this->workspace($request));

        return response()->json(['data' => $invoice]);
    }

    public function payments(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'processing', 'paid', 'refunded', 'failed', 'cancelled'])],
            'payment_method' => ['nullable', Rule::in(config('billing.payment_methods'))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = $this->workspace($request)->payments()->latest();
        $query->when(filled($attributes['status'] ?? null), fn ($query) => $query->where('status', $attributes['status']));
        $query->when(filled($attributes['payment_method'] ?? null), fn ($query) => $query->where('payment_method', $attributes['payment_method']));

        return ApiResponse::paginated($query->paginate(ApiResponse::perPage($request)));
    }

    public function cancelSubscription(Request $request, BillingService $billing): JsonResponse
    {
        $subscription = $this->workspace($request)->subscription()->firstOrFail();

        return response()->json(['data' => $billing->cancel($subscription, $request->boolean('at_period_end', true))]);
    }

    public function resumeSubscription(Request $request, BillingService $billing): JsonResponse
    {
        $subscription = $this->workspace($request)->subscription()->firstOrFail();

        return response()->json(['data' => $billing->resume($subscription)]);
    }
}
