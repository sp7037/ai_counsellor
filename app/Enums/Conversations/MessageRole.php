<?php

namespace App\Enums\Conversations;

enum MessageRole: string
{
    case Visitor = 'visitor';
    case System = 'system';
    case Assistant = 'assistant';
    case OfflineIntake = 'offline_intake';

    public function label(): string
    {
        return match ($this) {
            self::Visitor => 'Visitor',
            self::System => 'System',
            self::Assistant => 'Assistant',
            self::OfflineIntake => 'Offline intake',
        };
    }
}
