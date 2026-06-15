<?php

namespace App\Enums\Conversations;

enum ConversationActivityType: string
{
    case HandoffRequested = 'handoff_requested';
    case HandoffClaimed = 'handoff_claimed';
    case OwnershipTransferred = 'ownership_transferred';
    case OwnershipReleased = 'ownership_released';
    case AiPaused = 'ai_paused';
    case AiResumed = 'ai_resumed';
    case HumanUnavailable = 'human_unavailable';
    case Closed = 'closed';
    case Reopened = 'reopened';
    case CounsellorAssigned = 'counsellor_assigned';
    case LeadLinked = 'lead_linked';

    public function label(): string
    {
        return match ($this) {
            self::HandoffRequested => 'Human support requested',
            self::HandoffClaimed => 'Conversation claimed',
            self::OwnershipTransferred => 'Ownership transferred',
            self::OwnershipReleased => 'Human session ended',
            self::AiPaused => 'AI paused',
            self::AiResumed => 'AI resumed',
            self::HumanUnavailable => 'Human unavailable',
            self::Closed => 'Conversation closed',
            self::Reopened => 'Conversation reopened',
            self::CounsellorAssigned => 'Counsellor assigned',
            self::LeadLinked => 'Lead linked',
        };
    }
}
