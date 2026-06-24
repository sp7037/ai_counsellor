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
        'promote_after_messages' => 0,
        'subtle_label' => env('WIDGET_HANDOFF_SUBTLE_LABEL', 'Need human help?'),
        'offer_message' => env('WIDGET_HANDOFF_OFFER_MESSAGE', ''),
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

    'launcher_card' => [
        'title' => env('WIDGET_LAUNCHER_CARD_TITLE', 'Need help choosing MBBS abroad?'),
        'subtitle' => env('WIDGET_LAUNCHER_CARD_SUBTITLE', 'Ask your free admission counsellor about fees, eligibility, documents and country options.'),
        'cta_text' => env('WIDGET_LAUNCHER_CARD_CTA', 'Start free counselling'),
        'trust_text' => env('WIDGET_LAUNCHER_CARD_TRUST', 'Free guidance • No obligation'),
        'delay_seconds' => max(0, min(30, (int) env('WIDGET_LAUNCHER_CARD_DELAY', 5))),
        'dismiss_reshow_seconds' => max(3, min(10, (int) env('WIDGET_LAUNCHER_CARD_SNOOZE_SECONDS', 4))),
        'dismiss_hours' => max(1, min(168, (int) env('WIDGET_LAUNCHER_CARD_DISMISS_HOURS', 24))),
        'animation' => env('WIDGET_LAUNCHER_CARD_ANIMATION', 'soft_slide_up'),
    ],

    'default_welcome_message' => 'Hello! I am your AI counsellor. Ask me about services, admission, eligibility, fees, documents, or counselling support.',

];
