<?php

namespace App\Services\Billing;

use App\Contracts\BillingGateway;
use App\Models\BillingCheckoutSession;
use App\Models\Subscription;

class PendingBillingGateway implements BillingGateway
{
    public function configured(): bool
    {
        return false;
    }

    public function createCheckout(BillingCheckoutSession $checkout): array
    {
        return [
            'provider' => null,
            'reference' => null,
            'url' => null,
            'payload' => [
                'integration_status' => 'provider_selection_pending',
                'message' => 'O checkout está pronto para receber o adaptador do provedor de pagamentos.',
            ],
        ];
    }

    public function cancelSubscription(Subscription $subscription, bool $atPeriodEnd): void {}

    public function resumeSubscription(Subscription $subscription): void {}
}
