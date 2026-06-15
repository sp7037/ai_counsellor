<?php

namespace App\Services\Widget;

use App\Contracts\Knowledge\KnowledgeRetrievalContract;
use App\Enums\Conversations\ConversationStatus;
use App\Enums\Conversations\MessageRole;
use App\Models\AiRun;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\TenantSettings;
use App\Models\WidgetSession;
use App\Services\AI\AiConversationOrchestrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConversationService
{
    public function __construct(
        private readonly AiConversationOrchestrator $orchestrator,
        private readonly KnowledgeRetrievalContract $knowledgeRetrieval,
    ) {}

    public function addVisitorMessage(WidgetSession $session, string $body, ?string $requestId = null): array
    {
        if ($requestId !== null && Str::isUuid($requestId)) {
            $existingRun = AiRun::query()
                ->where('request_uuid', $requestId)
                ->where('conversation_id', $session->conversation_id)
                ->where('status', 'success')
                ->with('message')
                ->first();

            if ($existingRun?->message !== null) {
                $visitorMessage = Message::query()
                    ->where('conversation_id', $session->conversation_id)
                    ->where('role', MessageRole::Visitor->value)
                    ->latest('id')
                    ->first();

                return [
                    'visitor_message' => $visitorMessage ?? $existingRun->message,
                    'reply' => $existingRun->message,
                ];
            }
        }

        $body = trim(strip_tags($body));

        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'Message cannot be empty.']);
        }

        if (strlen($body) > config('widget.max_message_length', 4000)) {
            throw ValidationException::withMessages(['body' => 'Message is too long.']);
        }

        $conversation = $session->conversation;

        if ($conversation->status !== ConversationStatus::Open) {
            throw ValidationException::withMessages(['body' => 'Conversation is closed.']);
        }

        $visitorMessage = DB::transaction(function () use ($session, $body): Message {
            $conversation = $session->conversation;

            return Message::query()->create([
                'tenant_id' => $session->tenant_id,
                'conversation_id' => $conversation->id,
                'role' => MessageRole::Visitor->value,
                'body' => $body,
            ]);
        });

        $knowledge = $this->knowledgeRetrieval->searchPublished(
            $session->tenant,
            $body,
            (int) config('ai.max_knowledge_items', 6),
        );

        $aiResult = $this->orchestrator->respond(
            tenant: $session->tenant,
            conversation: $conversation,
            visitorMessage: $body,
            knowledge: $knowledge,
            requestId: $requestId,
        );

        $reply = DB::transaction(function () use ($session, $conversation, $aiResult): Message {
            $conversation->update(['last_message_at' => now()]);

            if ($aiResult['status'] === 'success' && is_string($aiResult['content'])) {
                $message = Message::query()->create([
                    'tenant_id' => $session->tenant_id,
                    'conversation_id' => $conversation->id,
                    'role' => MessageRole::Assistant->value,
                    'body' => $aiResult['content'],
                ]);

                $aiResult['run']->update(['message_id' => $message->id]);

                return $message;
            }

            $fallback = $this->safeFallbackMessage();

            return Message::query()->create([
                'tenant_id' => $session->tenant_id,
                'conversation_id' => $conversation->id,
                'role' => MessageRole::System->value,
                'body' => $fallback,
            ]);
        });

        return [
            'visitor_message' => $visitorMessage,
            'reply' => $reply,
        ];
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

    private function safeFallbackMessage(): string
    {
        $settings = TenantSettings::query()->first();
        $handover = trim((string) ($settings?->human_transfer_message ?? ''));

        if ($handover !== '') {
            return Str::limit($handover, (int) config('ai.max_output_chars', 3000), '');
        }

        return 'Our assistant is temporarily unavailable. Please try again shortly or contact our team.';
    }
}
