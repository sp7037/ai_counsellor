<?php

return [
    'default_grace_days' => (int) env('SUBSCRIPTION_DEFAULT_GRACE_DAYS', 7),
    'default_trial_days' => (int) env('SUBSCRIPTION_DEFAULT_TRIAL_DAYS', 14),
    'usage_warning_thresholds' => [75, 90, 100],
    'count_ai_runs' => 'successful_only',
    'widget_unavailable_message' => 'This service is temporarily unavailable. Please try again later or contact the organisation directly.',
    'widget_lead_capture_only_message' => 'Our assistant is unavailable right now. You can still leave your contact details and we will get back to you.',
    'human_conversation_continuity_on_expiry' => true,
];
