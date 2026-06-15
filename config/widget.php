<?php

return [

    'session_ttl_minutes' => (int) env('WIDGET_SESSION_TTL_MINUTES', 120),

    'rate_limit' => [
        'session_start' => env('WIDGET_RATE_LIMIT_SESSION', '20,1'),
        'messages' => env('WIDGET_RATE_LIMIT_MESSAGES', '60,1'),
    ],

    'allow_local_origins' => (bool) env('WIDGET_ALLOW_LOCAL_ORIGINS', true),

    'local_origins' => [
        'http://127.0.0.1:8000',
        'http://localhost:8000',
        'http://127.0.0.1',
        'http://localhost',
    ],

    'max_message_length' => 4000,

    'max_offline_message_length' => 2000,

];
