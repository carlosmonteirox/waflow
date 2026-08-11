<?php

namespace App\Contracts;

use App\Models\BillingCheckoutSession;
use App\Models\Subscription;

interface BillingGateway
{
    public function configured(): bool;

    /** @return array{provider: ?string, reference: ?string, url: ?string, payload: array<string, mixed>} */
    public function createCheckout(BillingCheckoutSession $checkout): array;

    public function cancelSubscription(Subscription $subscription, bool $atPeriodEnd): void;

    public function resumeSubscription(Subscription $subscription): void;
}
