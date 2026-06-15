<?php

namespace App\Enums\Conversations;

enum ConversationMode: string
{
    case Ai = 'ai';
    case HandoffRequested = 'handoff_requested';
    case Human = 'human';
    case HumanUnavailable = 'human_unavailable';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Ai => 'AI',
            self::HandoffRequested => 'Waiting for human',
            self::Human => 'Human',
            self::HumanUnavailable => 'Human unavailable',
            self::Closed => 'Closed',
        };
    }

    public function allowsAiResponse(): bool
    {
        return $this === self::Ai;
    }

    public function acceptsVisitorMessages(): bool
    {
        return in_array($this, [self::Ai, self::HandoffRequested, self::Human, self::HumanUnavailable], true);
    }
}
