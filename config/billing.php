<?php

return [
    'zarinpal' => [
        'enabled' => env('BILLING_ZARINPAL_ENABLED', false),
        'environment' => env('BILLING_ZARINPAL_ENVIRONMENT', 'sandbox'),
        'merchant_id' => env('BILLING_ZARINPAL_MERCHANT_ID'),
    ],
    'stripe' => [
        'secret_key' => env('BILLING_STRIPE_SECRET_KEY'),
        'webhook_secret' => env('BILLING_STRIPE_WEBHOOK_SECRET'),
        'currency' => env('BILLING_STRIPE_CURRENCY', 'USD'),
        'amount_minor' => env('BILLING_STRIPE_AMOUNT_MINOR'),
    ],
    'plan' => [
        'amount_irr' => env('BILLING_PLAN_AMOUNT_IRR'),
        'term_days' => env('BILLING_PLAN_TERM_DAYS', 30),
        'daily_verifications' => env('BILLING_PLAN_DAILY_VERIFICATIONS', 50000),
    ],
];
