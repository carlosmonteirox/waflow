<?php

return [
    'driver' => env('BILLING_DRIVER', 'pending'),
    'currency' => env('BILLING_CURRENCY', 'BRL'),
    'checkout_ttl_minutes' => (int) env('BILLING_CHECKOUT_TTL_MINUTES', 30),
    'payment_methods' => ['credit_card', 'debit_card', 'pix'],
    'available_payment_methods' => array_values(array_filter(explode(',', env(
        'BILLING_PAYMENT_METHODS',
        env('BILLING_DRIVER', 'pending') === 'inter' ? 'pix' : 'credit_card,debit_card,pix'
    )))),

    'inter' => [
        'environment' => env('INTER_ENVIRONMENT', 'sandbox'),
        'base_url' => env('INTER_BASE_URL', env('INTER_ENVIRONMENT', 'sandbox') === 'production'
            ? 'https://cdpj.partners.bancointer.com.br/pix/v2'
            : 'https://cdpj-sandbox.partners.uatinter.co/pix/v2'),
        'token_url' => env('INTER_TOKEN_URL', env('INTER_ENVIRONMENT', 'sandbox') === 'production'
            ? 'https://cdpj.partners.bancointer.com.br/oauth/v2/token'
            : 'https://cdpj-sandbox.partners.uatinter.co/oauth/v2/token'),
        'client_id' => env('INTER_CLIENT_ID'),
        'client_secret' => env('INTER_CLIENT_SECRET'),
        'certificate_path' => env('INTER_CERTIFICATE_PATH', '/run/secrets/inter.crt'),
        'private_key_path' => env('INTER_PRIVATE_KEY_PATH', '/run/secrets/inter.key'),
        'certificate_passphrase' => env('INTER_CERTIFICATE_PASSPHRASE'),
        'private_key_passphrase' => env('INTER_PRIVATE_KEY_PASSPHRASE'),
        'account' => env('INTER_ACCOUNT'),
        'scopes' => env('INTER_SCOPES', 'payloadlocationrec.read payloadlocationrec.write rec.read rec.write cobr.read cobr.write webhookrec.read webhookrec.write webhookcobr.read webhookcobr.write'),
        'webhook_secret' => env('INTER_WEBHOOK_SECRET'),
        'webhook_url' => env('INTER_WEBHOOK_URL'),
        'recurrence_years' => (int) env('INTER_RECURRENCE_YEARS', 5),
        'retry_policy' => env('INTER_RETRY_POLICY', 'PERMITE_3R_7D'),
        'charge_lead_days' => (int) env('INTER_CHARGE_LEAD_DAYS', 5),
        'receiver' => [
            'name' => env('INTER_RECEIVER_NAME'),
            'cnpj' => env('INTER_RECEIVER_CNPJ'),
            'agency' => env('INTER_RECEIVER_AGENCY'),
            'account' => env('INTER_RECEIVER_ACCOUNT'),
            'account_type' => env('INTER_RECEIVER_ACCOUNT_TYPE', 'CORRENTE'),
        ],
    ],
];
