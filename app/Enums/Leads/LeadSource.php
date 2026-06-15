<?php

namespace App\Enums\Leads;

enum LeadSource: string
{
    case WidgetConversation = 'widget_conversation';
    case WidgetForm = 'widget_form';
    case OfflineIntake = 'offline_intake';
    case Manual = 'manual';
    case ConversationConversion = 'conversation_conversion';

    public function label(): string
    {
        return match ($this) {
            self::WidgetConversation => 'Widget conversation',
            self::WidgetForm => 'Lead capture form',
            self::OfflineIntake => 'Offline intake',
            self::Manual => 'Manual entry',
            self::ConversationConversion => 'Conversation conversion',
        };
    }
}
