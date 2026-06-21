<?php

return [

    'session_ttl_minutes' => max(5, (int) env('WIDGET_SESSION_TTL_MINUTES', 120)),

    'rate_limit' => [
        'session_start' => env('WIDGET_RATE_LIMIT_SESSION', '20,1'),
        'messages' => env('WIDGET_RATE_LIMIT_MESSAGES', '60,1'),
    ],

    'allow_local_origins' => env('WIDGET_ALLOW_LOCAL_ORIGINS'),

    'local_origins' => [
        'http://127.0.0.1:8000',
        'http://localhost:8000',
        'http://127.0.0.1',
        'http://localhost',
    ],

    'max_message_length' => 4000,

    'max_offline_message_length' => 2000,

    'handoff' => [
        'promote_after_messages' => max(1, (int) env('WIDGET_HANDOFF_PROMOTE_AFTER_MESSAGES', 3)),
        'subtle_label' => env('WIDGET_HANDOFF_SUBTLE_LABEL', 'Need human help?'),
        'offer_message' => env('WIDGET_HANDOFF_OFFER_MESSAGE', 'You can continue here or request a human counsellor.'),
    ],

    'powered_by' => [
        'enabled' => (bool) env('WIDGET_POWERED_BY_ENABLED', true),
        'label' => env('WIDGET_POWERED_BY_LABEL', 'Powered by SR Worlds AI'),
    ],

    'launcher' => [
        // Optional Super Admin configured platform logo URL for the floating chat launcher.
        // When empty the widget falls back to the bundled platform logo, then tenant logo, then initials.
        'logo_url' => env('WIDGET_LAUNCHER_LOGO_URL', ''),
        // Subtle teaser label shown beside the floating launcher. Platform-level (not tenant-specific).
        'teaser_text' => env('WIDGET_LAUNCHER_TEASER_TEXT', 'Ask AI Counsellor'),
    ],

    'default_welcome_message' => 'Hello! I am your AI counsellor. Ask me about services, admission, eligibility, fees, documents, or counselling support.',

];
