<?php

namespace App\Services\Conversations;

use App\Enums\Conversations\ConversationMode;
use App\Enums\Conversations\MessageRole;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\WidgetSession;
use App\Services\AI\AiIdempotencyCoordinator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConversationMessageService
{
    public function __construct(
        private readonly ConversationReadStateService $readState,
        private readonly ConversationNotificationService $notifications,
        private readonly AiIdempotencyCoordinator $idempotency,
    ) {}

    public function sendCounsellorMessage(
        Conversation $conversation,
        User $counsellor,
        string $body,
        ?string $requestUuid = null,
    ): Message {
        $body = $this->sanitizeBody($body);

        if (! app(ConversationAccessService::class)->canSendAsCounsellor($counsellor, $conversation)) {
            throw ValidationException::withMessages(['body' => 'You do not own this conversation.']);
        }

        $requestUuid = $requestUuid ?? (string) Str::uuid();

        $existing = Message::query()
            ->where('tenant_id', $conversation->tenant_id)
            ->where('conversation_id', $conversation->id)
            ->where('request_uuid', $requestUuid)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $displayName = $counsellor->name;

        return DB::transaction(function () use ($conversation, $counsellor, $body, $requestUuid, $displayName): Message {
            $message = Message::query()->create([
                'tenant_id' => $conversation->tenant_id,
                'conversation_id' => $conversation->id,
                'request_uuid' => $requestUuid,
                'role' => MessageRole::Counsellor,
                'sender_user_id' => $counsellor->id,
                'sender_display_name' => $displayName,
                'body' => $body,
            ]);

            $conversation->update([
                'last_message_at' => now(),
                'last_human_message_at' => now(),
            ]);

            return $message;
        });
    }

    public function addVisitorMessageInHumanMode(
        WidgetSession $session,
        string $body,
        ?string $requestId = null,
    ): array {
        $body = $this->sanitizeBody($body);
        $conversation = $session->conversation;

        if ($conversation->status->value === 'closed' || $conversation->mode === ConversationMode::Closed) {
            throw ValidationException::withMessages(['body' => 'Conversation is closed.']);
        }

        if (! $conversation->mode->acceptsVisitorMessages()) {
            throw ValidationException::withMessages(['body' => 'Conversation is not accepting messages.']);
        }

        $resolved = $this->idempotency->resolveVisitorMessage(
            tenant: $session->tenant,
            conversation: $conversation,
            body: $body,
            clientRequestId: $requestId,
        );

        if ($resolved['replay'] !== null) {
            return [
                'visitor_message' => $resolved['visitor_message'],
                'reply' => $resolved['replay']['reply'] ?? null,
                'mode' => $conversation->mode->value,
            ];
        }

        DB::transaction(function () use ($conversation): void {
            $conversation->update([
                'last_message_at' => now(),
                'last_visitor_message_at' => now(),
            ]);

            $this->readState->incrementCounsellorUnread($conversation);
        });

        if ($conversation->human_owner_id !== null) {
            $this->notifications->newVisitorMessage($conversation, $conversation->human_owner_id);
        }

        return [
            'visitor_message' => $resolved['visitor_message'],
            'reply' => null,
            'mode' => $conversation->fresh()->mode->value,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listPublicMessages(Conversation $conversation, ?string $afterUuid = null, int $limit = 50): array
    {
        $query = $conversation->messages()
            ->orderBy('id');

        if ($afterUuid !== null) {
            $after = Message::query()
                ->where('conversation_id', $conversation->id)
                ->where('uuid', $afterUuid)
                ->first();

            if ($after !== null) {
                $query->where('id', '>', $after->id);
            }
        }

        return $query->limit($limit)
            ->get()
            ->filter(fn (Message $message) => $message->role->isPublicWidgetVisible())
            ->map(fn (Message $message) => $this->serializePublicMessage($message))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializePublicMessage(Message $message): array
    {
        $payload = [
            'uuid' => $message->uuid,
            'role' => $message->role->value,
            'body' => $message->body,
            'created_at' => $message->created_at?->toIso8601String(),
        ];

        if ($message->role === MessageRole::Counsellor && $message->sender_display_name) {
            $payload['sender_name'] = $message->sender_display_name;
        }

        return $payload;
    }

    private function sanitizeBody(string $body): string
    {
        $body = trim(strip_tags($body));

        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'Message cannot be empty.']);
        }

        if (strlen($body) > config('widget.max_message_length', 4000)) {
            throw ValidationException::withMessages(['body' => 'Message is too long.']);
        }

        return $body;
    }
}
