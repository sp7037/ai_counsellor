<?php

namespace App\Services\Widget;

use App\Contracts\Knowledge\KnowledgeRetrievalContract;
use App\Enums\Billing\PlanFeature;
use App\Enums\Billing\UsageMetric;
use App\Enums\Conversations\ConversationMode;
use App\Enums\Conversations\ConversationStatus;
use App\Enums\Conversations\MessageRole;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\TenantSettings;
use App\Models\WidgetSession;
use App\Services\AI\AiConversationOrchestrator;
use App\Services\AI\AiIdempotencyCoordinator;
use App\Services\AI\ConversationContextBuilder;
use App\Services\AI\CounsellingFlowHelper;
use App\Services\Billing\EntitlementResolver;
use App\Services\Billing\UsageTrackingService;
use App\Services\Billing\WidgetEntitlementService;
use App\Services\Conversations\ConversationMessageService;
use App\Services\Leads\ChatLeadExtractionService;
use App\Services\Leads\LeadNameGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConversationService
{
    public function __construct(
        private readonly AiConversationOrchestrator $orchestrator,
        private readonly KnowledgeRetrievalContract $knowledgeRetrieval,
        private readonly AiIdempotencyCoordinator $idempotency,
        private readonly ConversationMessageService $conversationMessages,
        private readonly WidgetEntitlementService $widgetEntitlements,
        private readonly EntitlementResolver $entitlements,
        private readonly UsageTrackingService $usage,
        private readonly ChatLeadExtractionService $leadExtraction,
        private readonly HandoffPromotionService $handoffPromotion,
        private readonly CounsellingFlowHelper $counsellingFlow,
        private readonly ConversationContextBuilder $contextBuilder,
        private readonly LeadNameGuard $nameGuard,
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
                'handoff_prominent' => true,
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
            $conversation->refresh()->loadMissing('lead');
            $context = $this->contextBuilder->build($conversation);
            $counsellingAssessment = $this->counsellingFlow->assess($conversation, $body, $context);

            return [
                'visitor_message' => $resolved['visitor_message'],
                'reply' => $resolved['replay']['reply'],
                'mode' => $conversation->mode->value,
                'handoff_prominent' => $this->handoffPromotion->evaluate(
                    $conversation,
                    $body,
                    $resolved['replay']['reply'],
                    [],
                )['prominent'],
                'show_location_chip' => $this->shouldOfferLocationChip($counsellingAssessment, $context),
            ];
        }

        $aiEntitlement = $this->widgetEntitlements->canUseAi($session->tenant);

        if (! $aiEntitlement->isAllowed()) {
            $visitorMessage = $resolved['visitor_message'];

            return [
                'visitor_message' => $visitorMessage,
                'reply' => null,
                'mode' => $conversation->mode->value,
                'handoff_prominent' => false,
            ];
        }

        $subscription = $this->entitlements->subscriptionFor($session->tenant);
        $limit = $this->entitlements->featureLimit($session->tenant, PlanFeature::AiResponses);
        $reserved = $subscription !== null && $this->usage->reserve(
            $session->tenant,
            UsageMetric::AiRuns,
            $subscription,
            1,
            $limit,
        );

        if ($subscription !== null && ! $reserved) {
            return [
                'visitor_message' => $resolved['visitor_message'],
                'reply' => null,
                'mode' => $conversation->mode->value,
                'handoff_prominent' => false,
            ];
        }

        $extracted = $this->leadExtraction->extractFromMessage($body);
        $contactCapturedThisMessage = $this->wasContactCaptured($extracted);

        $this->leadExtraction->processMessage($session->tenant, $conversation->fresh(), $body);
        $conversation->refresh()->loadMissing('lead');

        $context = $this->contextBuilder->build($conversation);
        $counsellingAssessment = $this->counsellingFlow->assess($conversation, $body, $context);

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

        if ($subscription !== null) {
            if ($aiResult['status'] === 'success' && is_string($aiResult['content'])) {
                $this->usage->confirmReservation($session->tenant, UsageMetric::AiRuns, $subscription);
            } else {
                $this->usage->releaseReservation($session->tenant, UsageMetric::AiRuns, $subscription);
            }
        }

        $reply = DB::transaction(function () use ($session, $conversation, $aiResult, $contactCapturedThisMessage, $counsellingAssessment, $context, $body): Message {
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

            if ($aiResult['status'] !== 'success') {
                Log::warning('Widget AI reply failed', [
                    'tenant_id' => $session->tenant_id,
                    'conversation_id' => $conversation->id,
                    'error_category' => $aiResult['error_category'] ?? null,
                    'contact_captured' => $contactCapturedThisMessage,
                    'counselling_active' => $counsellingAssessment['active'] ?? false,
                ]);
            }

            if ($contactCapturedThisMessage) {
                return Message::query()->create([
                    'tenant_id' => $session->tenant_id,
                    'conversation_id' => $conversation->id,
                    'role' => MessageRole::Assistant->value,
                    'body' => $this->contactDetailsSavedMessage(),
                    'metadata' => ['type' => 'contact_saved'],
                ]);
            }

            $counsellingFallback = $this->counsellingFlow->buildProviderFailureFallback(
                $counsellingAssessment,
                $context,
                $body,
            );

            if ($counsellingFallback !== null) {
                return Message::query()->create([
                    'tenant_id' => $session->tenant_id,
                    'conversation_id' => $conversation->id,
                    'role' => MessageRole::Assistant->value,
                    'body' => $counsellingFallback,
                    'metadata' => ['type' => 'counselling_continuity'],
                ]);
            }

            return Message::query()->create([
                'tenant_id' => $session->tenant_id,
                'conversation_id' => $conversation->id,
                'role' => MessageRole::System->value,
                'body' => $this->safeFallbackMessage($session->tenant_id),
                'metadata' => ['type' => 'provider_unavailable'],
            ]);
        });

        $fieldKey = $this->counsellingFlow->fieldKeyFromLabel($counsellingAssessment['next_field'] ?? null);
        $this->counsellingFlow->recordAskedField($conversation->fresh()->lead, $fieldKey);

        $handoff = $this->handoffPromotion->evaluate(
            $conversation->fresh(),
            $body,
            $reply,
            $knowledge,
        );

        return [
            'visitor_message' => $resolved['visitor_message'],
            'reply' => $reply,
            'mode' => $conversation->fresh()->mode->value,
            'handoff_prominent' => $handoff['prominent'],
            'show_location_chip' => $this->shouldOfferLocationChip($counsellingAssessment, $context),
        ];
    }

    /**
     * @param  array<string, mixed>  $counsellingAssessment
     * @param  array<string, mixed>  $context
     */
    private function shouldOfferLocationChip(array $counsellingAssessment, array $context): bool
    {
        if (! ($counsellingAssessment['active'] ?? false)) {
            return false;
        }

        if (! blank($context['city_state'] ?? null) || ! blank($context['location'] ?? null)) {
            return false;
        }

        if (($counsellingAssessment['next_field'] ?? null) === 'student city/state') {
            return true;
        }

        $metadata = is_array($context['metadata'] ?? null) ? $context['metadata'] : [];
        $askedFields = is_array($metadata['counselling_asked_fields'] ?? null)
            ? $metadata['counselling_asked_fields']
            : [];

        return in_array('student_city_state', $askedFields, true);
    }

    public function updateVisitorLocation(
        WidgetSession $session,
        string $city,
        ?string $state = null,
        ?float $latitude = null,
        ?float $longitude = null,
    ): void {
        $conversation = $session->conversation->fresh()->loadMissing('lead');
        $lead = $conversation->lead;

        if ($lead === null) {
            return;
        }

        $metadata = is_array($lead->metadata) ? $lead->metadata : [];
        $cityState = trim($city.($state ? ', '.$state : ''));

        if (blank($lead->location) && $city !== 'Nearby area') {
            $lead->location = $city;
        }

        if (blank($lead->state) && filled($state)) {
            $lead->state = $state;
        }

        if (blank($metadata['city_state'] ?? null) && $city !== 'Nearby area') {
            $metadata['city_state'] = $cityState;
        }

        $metadata['location_source'] = 'browser_geolocation';
        $metadata['location_consented'] = true;

        if ($latitude !== null && $longitude !== null) {
            $metadata['geo_latitude'] = $latitude;
            $metadata['geo_longitude'] = $longitude;
        }

        $lead->metadata = $metadata;
        $lead->save();
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

    private function contactDetailsSavedMessage(): string
    {
        return 'Thanks, I have saved your details. A counsellor can follow up if needed. Would you like to ask another question?';
    }

    /**
     * @param  array<string, mixed>  $extracted
     */
    private function wasContactCaptured(array $extracted): bool
    {
        if (! empty($extracted['mobile']) || ! empty($extracted['email'])) {
            return true;
        }

        return $this->nameGuard->isValidPersonName($extracted['full_name'] ?? null);
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
