<?php

namespace App\Contracts;

use App\Models\BillingCheckoutSession;
use App\Models\Subscription;

interface BillingGateway
{
    public function configured(): bool;

    /**
     * @param  array{name?: string, email?: string, tax_id?: string}  $payer
     * @return array{provider: ?string, reference: ?string, url: ?string, payload: array<string, mixed>}
     */
    public function createCheckout(BillingCheckoutSession $checkout, array $payer = []): array;

    public function cancelCheckout(BillingCheckoutSession $checkout): void;

    public function cancelSubscription(Subscription $subscription, bool $atPeriodEnd): void;

    public function resumeSubscription(Subscription $subscription): void;
}
