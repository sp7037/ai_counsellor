<?php

namespace App\Enums\Conversations;

enum ConversationChannel: string
{
    case Widget = 'widget';

    public function label(): string
    {
        return 'Widget';
    }
}
