<?php

namespace App\Services\Conversations;

use App\Enums\Conversations\MessageRole;
use App\Models\Conversation;
use App\Models\ConversationReadState;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConversationReadStateService
{
    public function markReadForCounsellor(Conversation $conversation, User $counsellor): void
    {
        if ($conversation->human_owner_id !== $counsellor->id && ! app(ConversationAccessService::class)->canSupervise($counsellor, $conversation->tenant)) {
            return;
        }

        $latestMessage = $conversation->messages()->orderByDesc('id')->first();

        ConversationReadState::query()->updateOrCreate(
            [
                'conversation_id' => $conversation->id,
                'user_id' => $counsellor->id,
            ],
            [
                'tenant_id' => $conversation->tenant_id,
                'last_read_message_id' => $latestMessage?->id,
                'last_read_at' => now(),
            ],
        );

        if ($conversation->human_owner_id === $counsellor->id) {
            $conversation->update(['counsellor_unread_count' => 0]);
        }
    }

    public function incrementCounsellorUnread(Conversation $conversation): void
    {
        if ($conversation->human_owner_id === null) {
            $conversation->increment('counsellor_unread_count');

            return;
        }

        Conversation::query()
            ->where('id', $conversation->id)
            ->update([
                'counsellor_unread_count' => DB::raw('counsellor_unread_count + 1'),
            ]);
    }

    public function unreadCountForCounsellor(User $counsellor, Conversation $conversation): int
    {
        if ($conversation->human_owner_id === $counsellor->id) {
            return (int) $conversation->counsellor_unread_count;
        }

        $state = ConversationReadState::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $counsellor->id)
            ->first();

        if ($state?->last_read_message_id === null) {
            return $conversation->messages()
                ->where('role', MessageRole::Visitor->value)
                ->count();
        }

        return $conversation->messages()
            ->where('role', MessageRole::Visitor->value)
            ->where('id', '>', $state->last_read_message_id)
            ->count();
    }
}
