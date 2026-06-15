<?php

namespace App\Services\Conversations;

use App\Enums\Conversations\ConversationActivityType;
use App\Enums\Conversations\ConversationMode;
use App\Enums\Conversations\ConversationStatus;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ConversationTransitionService
{
    public function __construct(
        private readonly ConversationActivityLogger $activity,
    ) {}

    public function transition(
        Conversation $conversation,
        ConversationMode $to,
        ?User $actor = null,
        ?string $reason = null,
        array $metadata = [],
    ): Conversation {
        $from = $conversation->mode;

        if ($from === $to) {
            return $conversation;
        }

        if (! $this->isAllowed($from, $to, $actor)) {
            throw ValidationException::withMessages([
                'mode' => "Transition from {$from->value} to {$to->value} is not permitted.",
            ]);
        }

        $updates = ['mode' => $to];

        if ($to === ConversationMode::HandoffRequested && $conversation->handoff_requested_at === null) {
            $updates['handoff_requested_at'] = now();
        }

        if ($to === ConversationMode::Human) {
            $updates['human_takeover_at'] = now();
        }

        if ($to === ConversationMode::Ai && $from === ConversationMode::Human) {
            $updates['human_released_at'] = now();
            $updates['human_owner_id'] = null;
        }

        if ($to === ConversationMode::Closed) {
            $updates['status'] = ConversationStatus::Closed;
            $updates['closed_at'] = now();
            $updates['close_reason'] = $reason;
            $updates['human_owner_id'] = null;
        }

        if ($from === ConversationMode::Closed && $to !== ConversationMode::Closed) {
            $updates['status'] = ConversationStatus::Open;
            $updates['closed_at'] = null;
            $updates['close_reason'] = null;
        }

        $conversation->update($updates);

        $activityType = match ($to) {
            ConversationMode::HandoffRequested => ConversationActivityType::HandoffRequested,
            ConversationMode::Human => ConversationActivityType::AiPaused,
            ConversationMode::Ai => ConversationActivityType::AiResumed,
            ConversationMode::HumanUnavailable => ConversationActivityType::HumanUnavailable,
            ConversationMode::Closed => ConversationActivityType::Closed,
            default => ConversationActivityType::AiResumed,
        };

        if ($from === ConversationMode::Closed) {
            $activityType = ConversationActivityType::Reopened;
        }

        $this->activity->log(
            $conversation->fresh(),
            $activityType,
            $actor,
            array_merge($metadata, array_filter(['reason' => $reason])),
            ['mode' => $from->value],
            ['mode' => $to->value],
        );

        return $conversation->fresh();
    }

    private function isAllowed(ConversationMode $from, ConversationMode $to, ?User $actor): bool
    {
        $allowed = match ($from) {
            ConversationMode::Ai => [
                ConversationMode::HandoffRequested,
                ConversationMode::Human,
                ConversationMode::Closed,
            ],
            ConversationMode::HandoffRequested => [
                ConversationMode::Human,
                ConversationMode::HumanUnavailable,
                ConversationMode::Ai,
                ConversationMode::Closed,
            ],
            ConversationMode::HumanUnavailable => [
                ConversationMode::Ai,
                ConversationMode::HandoffRequested,
                ConversationMode::Closed,
            ],
            ConversationMode::Human => [
                ConversationMode::Ai,
                ConversationMode::Closed,
            ],
            ConversationMode::Closed => $actor !== null && $this->canReopen($actor)
                ? [ConversationMode::Ai, ConversationMode::HandoffRequested]
                : [],
        };

        return in_array($to, $allowed, true);
    }

    private function canReopen(?User $actor): bool
    {
        if ($actor === null) {
            return false;
        }

        return true;
    }
}
