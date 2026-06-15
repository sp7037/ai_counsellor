<?php

namespace App\Services\Widget;

use App\Enums\Conversations\ConversationStatus;
use App\Enums\Conversations\MessageRole;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WidgetSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConversationService
{
    public function addVisitorMessage(WidgetSession $session, string $body): array
    {
        $body = trim($body);

        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'Message cannot be empty.']);
        }

        if (strlen($body) > config('widget.max_message_length', 4000)) {
            throw ValidationException::withMessages(['body' => 'Message is too long.']);
        }

        return DB::transaction(function () use ($session, $body): array {
            $conversation = $session->conversation;

            if ($conversation->status !== ConversationStatus::Open) {
                throw ValidationException::withMessages(['body' => 'Conversation is closed.']);
            }

            $visitorMessage = Message::query()->create([
                'tenant_id' => $session->tenant_id,
                'conversation_id' => $conversation->id,
                'role' => MessageRole::Visitor->value,
                'body' => $body,
            ]);

            $conversation->update(['last_message_at' => now()]);

            $systemReply = Message::query()->create([
                'tenant_id' => $session->tenant_id,
                'conversation_id' => $conversation->id,
                'role' => MessageRole::System->value,
                'body' => 'Thanks for your message. Our team will respond shortly.',
            ]);

            return [
                'visitor_message' => $visitorMessage,
                'system_reply' => $systemReply,
            ];
        });
    }

    public function submitOfflineIntake(
        WidgetSession $session,
        string $message,
        ?string $name = null,
        ?string $email = null,
    ): Message {
        $message = trim($message);

        if ($message === '') {
            throw ValidationException::withMessages(['message' => 'Message cannot be empty.']);
        }

        if (strlen($message) > config('widget.max_offline_message_length', 2000)) {
            throw ValidationException::withMessages(['message' => 'Message is too long.']);
        }

        return DB::transaction(function () use ($session, $message, $name, $email): Message {
            $conversation = $session->conversation;

            $intake = Message::query()->create([
                'tenant_id' => $session->tenant_id,
                'conversation_id' => $conversation->id,
                'role' => MessageRole::OfflineIntake->value,
                'body' => $message,
                'metadata' => array_filter([
                    'name' => $name,
                    'email' => $email,
                ]),
            ]);

            $conversation->update(['last_message_at' => now()]);

            return $intake;
        });
    }

    public function listMessages(Conversation $conversation): array
    {
        return $conversation->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn (Message $message) => [
                'uuid' => $message->uuid,
                'role' => $message->role->value,
                'body' => $message->body,
                'created_at' => $message->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
