<?php

namespace App\Enums\Conversations;

enum MessageRole: string
{
    case Visitor = 'visitor';
    case System = 'system';
    case Assistant = 'assistant';
    case Counsellor = 'counsellor';
    case OfflineIntake = 'offline_intake';

    public function label(): string
    {
        return match ($this) {
            self::Visitor => 'Visitor',
            self::System => 'System',
            self::Assistant => 'Assistant',
            self::Counsellor => 'Counsellor',
            self::OfflineIntake => 'Offline intake',
        };
    }

    public function isPublicWidgetVisible(): bool
    {
        return in_array($this, [self::Visitor, self::Assistant, self::Counsellor, self::System], true);
    }
}
