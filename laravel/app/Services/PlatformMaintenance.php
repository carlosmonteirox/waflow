<?php

namespace App\Services;

use App\Contracts\BillingGateway;
use App\Jobs\DeliverWorkspaceWebhook;
use App\Models\BillingCheckoutSession;
use App\Models\Subscription;
use App\Models\TwoFactorCode;
use App\Models\WebhookDelivery;
use App\Models\WorkspaceInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PlatformMaintenance
{
    public function __construct(private readonly BillingGateway $billingGateway) {}

    /** @return array{checkouts: int, invitations: int, subscriptions: int, two_factor_codes: int, webhook_deliveries: int} */
    public function expireTemporaryRecords(): array
    {
        $result = DB::transaction(function (): array {
            $invitations = WorkspaceInvitation::query()
                ->where('status', 'pending')
                ->where('expires_at', '<=', now())
                ->update(['status' => 'expired']);

            $twoFactorCodes = TwoFactorCode::query()
                ->where(fn ($query) => $query->where('expires_at', '<=', now()->subDay())->orWhere('consumed_at', '<=', now()->subDay()))
                ->delete();

            return [
                'checkouts' => 0,
                'invitations' => $invitations,
                'subscriptions' => 0,
                'two_factor_codes' => $twoFactorCodes,
            ];
        });

        $checkouts = BillingCheckoutSession::query()
            ->whereIn('status', ['draft', 'provider_pending', 'pending'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();
        foreach ($checkouts as $checkout) {
            try {
                $this->billingGateway->cancelCheckout($checkout);
                $checkout->update(['status' => 'expired']);
                $result['checkouts']++;
            } catch (\Throwable $exception) {
                Log::error('Falha ao expirar checkout no provedor.', [
                    'checkout_id' => $checkout->id,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $subscriptions = Subscription::query()
            ->where('cancel_at_period_end', true)
            ->whereNotNull('current_period_ends_at')
            ->where('current_period_ends_at', '<=', now())
            ->get();
        foreach ($subscriptions as $subscription) {
            try {
                $this->billingGateway->cancelSubscription($subscription, false);
                $subscription->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_at_period_end' => false]);
                $result['subscriptions']++;
            } catch (\Throwable $exception) {
                Log::error('Falha ao concluir cancelamento de assinatura.', [
                    'subscription_id' => $subscription->id,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $pendingDeliveries = WebhookDelivery::query()
            ->where('status', 'pending')
            ->whereNotNull('payload')
            ->where('updated_at', '<=', now()->subMinutes(5))
            ->limit(100)
            ->get();
        foreach ($pendingDeliveries as $delivery) {
            DeliverWorkspaceWebhook::dispatch($delivery->id, $delivery->payload);
        }
        $result['webhook_deliveries'] = $pendingDeliveries->count();

        return $result;
    }
}
