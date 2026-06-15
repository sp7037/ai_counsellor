<?php

namespace App\Services\Conversations;

use App\Models\Conversation;
use App\Models\LeadNotification;
use App\Models\User;

class ConversationNotificationService
{
    public function handoffRequested(Conversation $conversation, int $userId): void
    {
        $this->create($conversation, $userId, 'handoff_requested', 'Human support requested', 'A visitor is waiting for human support.');
    }

    public function handoffClaimed(Conversation $conversation, User $counsellor): void
    {
        $conversation->loadMissing('lead');

        if ($conversation->lead?->assigned_to !== null && $conversation->lead->assigned_to !== $counsellor->id) {
            $this->create(
                $conversation,
                $conversation->lead->assigned_to,
                'conversation_claimed',
                'Conversation claimed',
                'Another counsellor claimed a conversation linked to your lead.',
            );
        }
    }

    public function conversationAssigned(Conversation $conversation, User $counsellor, User $admin): void
    {
        $this->create(
            $conversation,
            $counsellor->id,
            'conversation_assigned',
            'Conversation assigned',
            'A conversation has been assigned to you.',
        );
    }

    public function newVisitorMessage(Conversation $conversation, int $counsellorId): void
    {
        $this->create(
            $conversation,
            $counsellorId,
            'new_visitor_message',
            'New visitor message',
            'You have a new message in an active conversation.',
        );
    }

    private function create(Conversation $conversation, int $userId, string $type, string $title, string $body): void
    {
        try {
            LeadNotification::query()->create([
                'tenant_id' => $conversation->tenant_id,
                'user_id' => $userId,
                'lead_id' => $conversation->lead_id,
                'conversation_id' => $conversation->id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
            ]);
        } catch (\Throwable) {
            // Notification failure must not break workflows.
        }
    }
}
