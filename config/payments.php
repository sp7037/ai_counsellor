<?php

return [
    'default_provider' => env('PAYMENT_DEFAULT_PROVIDER', 'razorpay'),
    'environment' => env('PAYMENT_ENVIRONMENT', 'test'),
    'order_expiry_minutes' => (int) env('PAYMENT_ORDER_EXPIRY_MINUTES', 30),
    'platform_legal_name' => env('PAYMENT_PLATFORM_LEGAL_NAME', config('app.name')),
    'connect_timeout_seconds' => (int) env('PAYMENT_CONNECT_TIMEOUT', 5),
    'request_timeout_seconds' => (int) env('PAYMENT_REQUEST_TIMEOUT', 15),
    'http_retries' => 0,
    'webhook_rate_limit' => env('PAYMENT_WEBHOOK_RATE_LIMIT', '120,1'),
    'reconciliation_batch_size' => (int) env('PAYMENT_RECONCILIATION_BATCH', 50),
    'providers' => [
        'fake' => [
            'enabled' => (bool) env('FAKE_PAYMENT_ENABLED', true),
            'key_id' => env('FAKE_PAYMENT_KEY_ID', 'rzp_test_fake'),
            'key_secret' => env('FAKE_PAYMENT_KEY_SECRET', 'fake_secret_for_tests_only'),
            'webhook_secret' => env('FAKE_PAYMENT_WEBHOOK_SECRET', 'fake_webhook_secret'),
        ],
        'razorpay' => [
            'base_url' => env('RAZORPAY_BASE_URL', 'https://api.razorpay.com/v1'),
            'key_id' => env('RAZORPAY_KEY_ID'),
            'key_secret' => env('RAZORPAY_KEY_SECRET'),
            'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
            'enabled' => (bool) env('RAZORPAY_ENABLED', false),
        ],
    ],
];
