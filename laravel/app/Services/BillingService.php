<?php

namespace App\Services;

use App\Contracts\BillingGateway;
use App\Models\BillingCheckoutSession;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillingService
{
    public function __construct(private readonly BillingGateway $gateway) {}

    /** @param array{name?: string, email?: string, tax_id?: string} $payer */
    public function createCheckout(Workspace $workspace, User $user, Plan $plan, string $paymentMethod, array $payer = []): BillingCheckoutSession
    {
        if (! in_array($paymentMethod, config('billing.available_payment_methods', []), true)) {
            throw ValidationException::withMessages(['payment_method' => 'Forma de pagamento não suportada.']);
        }

        return DB::transaction(function () use ($workspace, $user, $plan, $paymentMethod, $payer): BillingCheckoutSession {
            Workspace::query()->whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            $openCheckouts = $workspace->checkoutSessions()->whereIn('status', ['draft', 'provider_pending', 'pending'])
                ->lockForUpdate()->get();
            foreach ($openCheckouts as $openCheckout) {
                $this->gateway->cancelCheckout($openCheckout);
                $openCheckout->update(['status' => 'cancelled']);
            }

            $checkout = $workspace->checkoutSessions()->create([
                'plan_id' => $plan->id,
                'created_by' => $user->id,
                'payment_method' => $paymentMethod,
                'status' => 'draft',
                'amount_cents' => $plan->monthly_price_cents,
                'currency' => config('billing.currency', 'BRL'),
                'expires_at' => now()->addMinutes((int) config('billing.checkout_ttl_minutes', 30)),
            ]);

            $provider = $this->gateway->createCheckout($checkout, array_merge([
                'name' => $user->name,
                'email' => $user->email,
            ], $payer));
            $checkout->update([
                'status' => $this->gateway->configured() ? 'pending' : 'provider_pending',
                'provider' => $provider['provider'],
                'provider_reference' => $provider['reference'],
                'checkout_url' => $provider['url'],
                'provider_payload' => $provider['payload'],
            ]);

            return $checkout->fresh('plan');
        });
    }

    public function cancelCheckout(BillingCheckoutSession $checkout): BillingCheckoutSession
    {
        abort_if(in_array($checkout->status, ['completed', 'paid'], true), 409, 'Um checkout concluído não pode ser cancelado.');
        $this->gateway->cancelCheckout($checkout);
        $checkout->update(['status' => 'cancelled']);

        return $checkout->fresh();
    }

    public function cancel(Subscription $subscription, bool $atPeriodEnd = true): Subscription
    {
        $this->gateway->cancelSubscription($subscription, $atPeriodEnd);
        $subscription->update([
            'cancel_at_period_end' => $atPeriodEnd,
            'status' => $atPeriodEnd ? $subscription->status : 'cancelled',
            'cancelled_at' => $atPeriodEnd ? null : now(),
        ]);

        return $subscription->fresh();
    }

    public function resume(Subscription $subscription): Subscription
    {
        $this->gateway->resumeSubscription($subscription);
        $subscription->update(['cancel_at_period_end' => false, 'cancelled_at' => null]);

        return $subscription->fresh();
    }
}
