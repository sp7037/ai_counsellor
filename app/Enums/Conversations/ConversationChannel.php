<?php

namespace App\Enums\Conversations;

enum ConversationChannel: string
{
    case Widget = 'widget';
    case WhatsApp = 'whatsapp';

    public function label(): string
    {
        return match ($this) {
            self::Widget => 'Widget',
            self::WhatsApp => 'WhatsApp',
        };
    }
}
