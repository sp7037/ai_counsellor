<?php

return [
    'default_provider' => env('AI_DEFAULT_PROVIDER', 'openai'),
    'request_timeout_seconds' => (int) env('AI_REQUEST_TIMEOUT', 15),
    'connect_timeout_seconds' => (int) env('AI_CONNECT_TIMEOUT', 5),
    'max_input_chars' => 8000,
    'max_output_chars' => 3000,
    'max_history_messages' => 12,
    'max_knowledge_items' => 5,
    'knowledge_excerpt_chars' => 280,
    'recommended_counselling_output_tokens' => 320,
    'min_output_tokens' => 240,
    'max_counselling_reply_words' => 180,
    'max_temperature' => 1.2,
    'min_temperature' => 0.0,
    'max_output_tokens_limit' => 1200,
    'allowed_models' => [
        'gpt-4o-mini',
        'gpt-4o',
        'deepseek-v4-flash',
        'deepseek-v4-pro',
        'fake-model',
    ],
    'http_retries' => 0,
    'rate_limit' => [
        'messages' => env('AI_RATE_LIMIT_MESSAGES', '30,1'),
    ],
    'providers' => [
        'fake' => [
            'model' => env('FAKE_AI_MODEL', 'fake-model'),
            'temperature' => 0.0,
            'max_output_tokens' => 200,
            'enabled' => (bool) env('FAKE_AI_ENABLED', true),
        ],
        'openai' => [
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'temperature' => (float) env('OPENAI_TEMPERATURE', 0.2),
            'max_output_tokens' => (int) env('OPENAI_MAX_OUTPUT_TOKENS', 480),
            'enabled' => (bool) env('OPENAI_ENABLED', true),
        ],
        'deepseek' => [
            'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
            'api_key' => env('DEEPSEEK_API_KEY'),
            'model' => env('DEEPSEEK_MODEL', 'deepseek-v4-flash'),
            'temperature' => (float) env('DEEPSEEK_TEMPERATURE', 0.2),
            'max_output_tokens' => (int) env('DEEPSEEK_MAX_OUTPUT_TOKENS', 480),
            'enabled' => (bool) env('DEEPSEEK_ENABLED', true),
        ],
    ],
];
