<?php

namespace App\Services;

use App\Jobs\DeliverWorkspaceWebhook;
use App\Models\BillingCheckoutSession;
use App\Models\Subscription;
use App\Models\TwoFactorCode;
use App\Models\WebhookDelivery;
use App\Models\WorkspaceInvitation;
use Illuminate\Support\Facades\DB;

class PlatformMaintenance
{
    /** @return array{checkouts: int, invitations: int, subscriptions: int, two_factor_codes: int, webhook_deliveries: int} */
    public function expireTemporaryRecords(): array
    {
        $result = DB::transaction(function (): array {
            $checkouts = BillingCheckoutSession::query()
                ->whereIn('status', ['draft', 'provider_pending', 'pending'])
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->update(['status' => 'expired']);

            $invitations = WorkspaceInvitation::query()
                ->where('status', 'pending')
                ->where('expires_at', '<=', now())
                ->update(['status' => 'expired']);

            $subscriptions = Subscription::query()
                ->where('cancel_at_period_end', true)
                ->whereNotNull('current_period_ends_at')
                ->where('current_period_ends_at', '<=', now())
                ->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_at_period_end' => false]);

            $twoFactorCodes = TwoFactorCode::query()
                ->where(fn ($query) => $query->where('expires_at', '<=', now()->subDay())->orWhere('consumed_at', '<=', now()->subDay()))
                ->delete();

            return [
                'checkouts' => $checkouts,
                'invitations' => $invitations,
                'subscriptions' => $subscriptions,
                'two_factor_codes' => $twoFactorCodes,
            ];
        });

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
