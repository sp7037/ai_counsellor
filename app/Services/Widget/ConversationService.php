<?php

namespace App\Services\Widget;

use App\Contracts\Knowledge\KnowledgeRetrievalContract;
use App\Enums\Conversations\ConversationMode;
use App\Enums\Conversations\ConversationStatus;
use App\Enums\Conversations\MessageRole;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\TenantSettings;
use App\Models\WidgetSession;
use App\Services\AI\AiConversationOrchestrator;
use App\Services\AI\AiIdempotencyCoordinator;
use App\Services\Conversations\ConversationMessageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConversationService
{
    public function __construct(
        private readonly AiConversationOrchestrator $orchestrator,
        private readonly KnowledgeRetrievalContract $knowledgeRetrieval,
        private readonly AiIdempotencyCoordinator $idempotency,
        private readonly ConversationMessageService $conversationMessages,
    ) {}

    public function addVisitorMessage(WidgetSession $session, string $body, ?string $requestId = null): array
    {
        $conversation = $session->conversation;

        if ($conversation->status !== ConversationStatus::Open || $conversation->mode === ConversationMode::Closed) {
            throw ValidationException::withMessages(['body' => 'Conversation is closed.']);
        }

        if (! $conversation->mode->allowsAiResponse()) {
            $humanResult = $this->conversationMessages->addVisitorMessageInHumanMode($session, $body, $requestId);

            return [
                'visitor_message' => $humanResult['visitor_message'],
                'reply' => $humanResult['reply'] ?? $this->waitingReply($conversation),
                'mode' => $humanResult['mode'],
            ];
        }

        $body = trim(strip_tags($body));

        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'Message cannot be empty.']);
        }

        if (strlen($body) > config('widget.max_message_length', 4000)) {
            throw ValidationException::withMessages(['body' => 'Message is too long.']);
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
                'reply' => $resolved['replay']['reply'],
                'mode' => $conversation->mode->value,
            ];
        }

        $knowledge = $this->knowledgeRetrieval->searchPublished(
            $session->tenant,
            $body,
            (int) config('ai.max_knowledge_items', 6),
        );

        $aiResult = $this->orchestrator->respond(
            tenant: $session->tenant,
            conversation: $conversation,
            triggeringMessage: $resolved['visitor_message'],
            visitorMessage: $body,
            knowledge: $knowledge,
            requestUuid: $resolved['request_uuid'],
        );

        $reply = DB::transaction(function () use ($session, $conversation, $aiResult): Message {
            $conversation->update([
                'last_message_at' => now(),
                'last_visitor_message_at' => now(),
            ]);

            if ($aiResult['status'] === 'success' && is_string($aiResult['content'])) {
                if ($aiResult['run']->message !== null) {
                    return $aiResult['run']->message;
                }

                $finalized = app(AiIdempotencyCoordinator::class)->finalizeSuccess(
                    $aiResult['run'],
                    $aiResult['content'],
                );

                return $finalized['assistant'];
            }

            return Message::query()->create([
                'tenant_id' => $session->tenant_id,
                'conversation_id' => $conversation->id,
                'role' => MessageRole::System->value,
                'body' => $this->safeFallbackMessage($session->tenant_id),
            ]);
        });

        return [
            'visitor_message' => $resolved['visitor_message'],
            'reply' => $reply,
            'mode' => $conversation->fresh()->mode->value,
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
            ->filter(fn (Message $message) => $message->role->isPublicWidgetVisible())
            ->map(fn (Message $message) => app(ConversationMessageService::class)->serializePublicMessage($message))
            ->all();
    }

    private function safeFallbackMessage(int $tenantId): string
    {
        $settings = TenantSettings::query()->where('tenant_id', $tenantId)->first();
        $handover = trim((string) ($settings?->human_transfer_message ?? ''));

        if ($handover !== '') {
            return Str::limit($handover, (int) config('ai.max_output_chars', 3000), '');
        }

        return 'Our assistant is temporarily unavailable. Please try again shortly or contact our team.';
    }

    private function waitingReply(Conversation $conversation): ?Message
    {
        if ($conversation->mode !== ConversationMode::HandoffRequested) {
            return null;
        }

        return $conversation->messages()
            ->where('role', MessageRole::System->value)
            ->where('metadata->type', 'handoff_ack')
            ->latest('id')
            ->first();
    }
}
