<?php

return [
    'default_provider' => env('MESSAGING_DEFAULT_PROVIDER', 'meta'),
    'environment' => env('MESSAGING_ENVIRONMENT', 'test'),
    'service_window_hours' => (int) env('MESSAGING_SERVICE_WINDOW_HOURS', 24),
    'webhook_rate_limit' => env('MESSAGING_WEBHOOK_RATE_LIMIT', '240,1'),
    'connect_timeout_seconds' => (int) env('MESSAGING_CONNECT_TIMEOUT', 5),
    'request_timeout_seconds' => (int) env('MESSAGING_REQUEST_TIMEOUT', 15),
    'http_retries' => 0,
    'providers' => [
        'fake' => [
            'enabled' => (bool) env('FAKE_MESSAGING_ENABLED', true),
            'base_url' => env('FAKE_MESSAGING_BASE_URL', 'https://fake-messaging.test/v1'),
            'app_secret' => env('FAKE_MESSAGING_APP_SECRET', 'fake_app_secret_for_tests'),
        ],
        'meta' => [
            'enabled' => (bool) env('META_WHATSAPP_ENABLED', false),
            'base_url' => env('META_WHATSAPP_BASE_URL', 'https://graph.facebook.com/v21.0'),
            'graph_version' => env('META_WHATSAPP_GRAPH_VERSION', 'v21.0'),
        ],
    ],
];
