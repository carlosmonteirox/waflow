<?php

return [
    'trial_days' => (int) env('WAFLOW_TRIAL_DAYS', 7),
    'trial_limits' => [
        'instances' => (int) env('WAFLOW_TRIAL_INSTANCE_LIMIT', 1),
        'messages' => (int) env('WAFLOW_TRIAL_MESSAGE_LIMIT', 500),
        'webhooks' => (int) env('WAFLOW_TRIAL_WEBHOOK_LIMIT', 2),
        'api_keys' => (int) env('WAFLOW_TRIAL_API_KEY_LIMIT', 1),
        'portals' => (int) env('WAFLOW_TRIAL_PORTAL_LIMIT', 1),
    ],
    'registration' => [
        'max_per_ip_30_days' => (int) env('WAFLOW_TRIALS_PER_IP', 2),
        'blocked_email_domains' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'WAFLOW_BLOCKED_EMAIL_DOMAINS',
            'mailinator.com,guerrillamail.com,10minutemail.com,temp-mail.org,yopmail.com,throwawaymail.com,sharklasers.com,dispostable.com',
        ))))),
        'public_email_domains' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'WAFLOW_PUBLIC_EMAIL_DOMAINS',
            'gmail.com,googlemail.com,outlook.com,hotmail.com,live.com,yahoo.com,icloud.com,proton.me,protonmail.com,uol.com.br,bol.com.br,terra.com.br',
        ))))),
    ],
    'api' => [
        'requests_per_minute' => (int) env('WAFLOW_API_REQUESTS_PER_MINUTE', 120),
        'authentication_attempts_per_ip' => (int) env('WAFLOW_API_AUTH_ATTEMPTS_PER_IP', 300),
        'authentication_attempts_per_credential' => (int) env('WAFLOW_API_AUTH_ATTEMPTS_PER_CREDENTIAL', 120),
        'message_writes_per_minute' => (int) env('WAFLOW_MESSAGE_WRITES_PER_MINUTE', 60),
        'bulk_requests_per_minute' => (int) env('WAFLOW_BULK_REQUESTS_PER_MINUTE', 10),
        'sensitive_writes_per_minute' => (int) env('WAFLOW_SENSITIVE_WRITES_PER_MINUTE', 20),
        'max_base64_bytes' => (int) env('WAFLOW_MAX_BASE64_BYTES', 10 * 1024 * 1024),
        'allowed_abilities' => [
            'workspace:read', 'workspace:write', 'instances:read', 'instances:write',
            'messages:read', 'messages:write', 'contacts:read', 'contacts:write',
            'flows:read', 'flows:write',
            'groups:read', 'channels:read', 'webhooks:read', 'webhooks:write',
            'portals:read', 'portals:write', 'billing:read', 'billing:write',
            'api-keys:read', 'api-keys:write', 'team:read', 'team:write',
        ],
    ],
    'webhooks' => [
        'openwa_requests_per_minute' => (int) env('WAFLOW_OPENWA_WEBHOOK_REQUESTS_PER_MINUTE', 300),
        'inter_requests_per_minute' => (int) env('WAFLOW_INTER_WEBHOOK_REQUESTS_PER_MINUTE', 60),
        'max_payload_bytes' => (int) env('WAFLOW_WEBHOOK_MAX_PAYLOAD_BYTES', 1024 * 1024),
    ],
    'two_factor' => [
        'log_codes' => (bool) env('WAFLOW_LOG_TWO_FACTOR_CODES', false),
    ],
];
