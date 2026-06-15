<?php

namespace App\Services\Conversations;

use App\Enums\Conversations\ConversationActivityType;
use App\Models\Conversation;
use App\Models\ConversationActivity;
use App\Models\User;

class ConversationActivityLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>|null  $previous
     * @param  array<string, mixed>|null  $new
     */
    public function log(
        Conversation $conversation,
        ConversationActivityType $type,
        ?User $actor = null,
        array $metadata = [],
        ?array $previous = null,
        ?array $new = null,
    ): ConversationActivity {
        return ConversationActivity::query()->create([
            'tenant_id' => $conversation->tenant_id,
            'conversation_id' => $conversation->id,
            'actor_user_id' => $actor?->id,
            'action_type' => $type,
            'metadata' => $metadata === [] ? null : $metadata,
            'previous_values' => $previous,
            'new_values' => $new,
            'created_at' => now(),
        ]);
    }
}
