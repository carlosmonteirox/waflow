<?php

return [
    // The commercial provider will replace the pending driver without changing controllers or screens.
    'driver' => env('BILLING_DRIVER', 'pending'),
    'currency' => env('BILLING_CURRENCY', 'BRL'),
    'checkout_ttl_minutes' => (int) env('BILLING_CHECKOUT_TTL_MINUTES', 30),
    'payment_methods' => ['credit_card', 'debit_card', 'pix'],
];
