<?php

return [

    'poll_interval_seconds' => (int) env('CONVERSATION_POLL_INTERVAL', 5),

    'max_poll_messages' => (int) env('CONVERSATION_MAX_POLL_MESSAGES', 50),

    'max_initial_history' => (int) env('CONVERSATION_MAX_INITIAL_HISTORY', 100),

    'handoff_acknowledgement' => 'Thank you. A counsellor will join you shortly.',

    'human_unavailable_message' => 'Our counsellors are currently unavailable. The assistant can continue helping you.',

    'closed_message' => 'This conversation has been closed. You may start a new chat if you need further help.',

    'rate_limit' => [
        'handoff' => env('WIDGET_RATE_LIMIT_HANDOFF', '10,1'),
        'poll' => env('WIDGET_RATE_LIMIT_POLL', '120,1'),
    ],

];
