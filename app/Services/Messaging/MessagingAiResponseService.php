<?php

namespace App\Services\Messaging;

use App\Contracts\Knowledge\KnowledgeRetrievalContract;
use App\Enums\Billing\PlanFeature;
use App\Enums\Billing\UsageMetric;
use App\Enums\Conversations\ConversationMode;
use App\Enums\Conversations\MessageRole;
use App\Enums\Messaging\MessagingEventType;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\TenantSettings;
use App\Services\AI\AiConversationOrchestrator;
use App\Services\AI\AiIdempotencyCoordinator;
use App\Services\Billing\EntitlementResolver;
use App\Services\Billing\UsageTrackingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MessagingAiResponseService
{
    public function __construct(
        private readonly AiConversationOrchestrator $orchestrator,
        private readonly KnowledgeRetrievalContract $knowledgeRetrieval,
        private readonly AiIdempotencyCoordinator $idempotency,
        private readonly EntitlementResolver $entitlements,
        private readonly UsageTrackingService $usage,
        private readonly OutboundMessageService $outbound,
        private readonly MessagingEventRecorder $events,
    ) {}

    public function respondToInbound(Conversation $conversation, Message $triggeringMessage, string $visitorBody): ?Message
    {
        if ($conversation->mode !== ConversationMode::Ai) {
            return null;
        }

        $tenant = $conversation->tenant;

        $aiEntitlement = $this->entitlements->check($tenant, PlanFeature::AiResponses);

        if (! $aiEntitlement->isAllowed()) {
            return null;
        }

        $this->events->record(
            MessagingEventType::AiGenerationRequested,
            $conversation->messagingIntegration,
            $conversation,
            $triggeringMessage,
        );

        $subscription = $this->entitlements->subscriptionFor($tenant);
        $limit = $this->entitlements->featureLimit($tenant, PlanFeature::AiResponses);
        $reserved = $subscription !== null && $this->usage->reserve(
            $tenant,
            UsageMetric::AiRuns,
            $subscription,
            1,
            $limit,
        );

        if ($subscription !== null && ! $reserved) {
            return null;
        }

        $knowledge = $this->knowledgeRetrieval->searchPublished(
            $tenant,
            $visitorBody,
            (int) config('ai.max_knowledge_items', 6),
        );

        $requestUuid = (string) Str::uuid();

        $aiResult = $this->orchestrator->respond(
            tenant: $tenant,
            conversation: $conversation,
            triggeringMessage: $triggeringMessage,
            visitorMessage: $visitorBody,
            knowledge: $knowledge,
            requestUuid: $requestUuid,
        );

        if ($subscription !== null) {
            if ($aiResult['status'] === 'success' && is_string($aiResult['content'])) {
                $this->usage->confirmReservation($tenant, UsageMetric::AiRuns, $subscription);
            } else {
                $this->usage->releaseReservation($tenant, UsageMetric::AiRuns, $subscription);
            }
        }

        return DB::transaction(function () use ($conversation, $tenant, $aiResult): ?Message {
            if ($aiResult['status'] === 'success' && is_string($aiResult['content'])) {
                if ($aiResult['run']->message !== null) {
                    $assistant = $aiResult['run']->message;
                } else {
                    $finalized = $this->idempotency->finalizeSuccess(
                        $aiResult['run'],
                        $aiResult['content'],
                    );
                    $assistant = $finalized['assistant'];
                }

                $this->outbound->sendAssistantReply($conversation, $assistant);

                return $assistant;
            }

            $fallback = Message::query()->create([
                'tenant_id' => $tenant->id,
                'conversation_id' => $conversation->id,
                'role' => MessageRole::System->value,
                'body' => $this->safeFallbackMessage($tenant->id),
            ]);

            $this->outbound->sendAssistantReply($conversation, $fallback);

            return $fallback;
        });
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
}
