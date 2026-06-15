<?php

namespace App\Services\Messaging;

use App\Data\Messaging\ProviderSendMessageRequest;
use App\Enums\Billing\PlanFeature;
use App\Enums\Conversations\ConversationChannel;
use App\Enums\Messaging\MessageDeliveryState;
use App\Enums\Messaging\MessageDirection;
use App\Enums\Messaging\MessagingEventType;
use App\Enums\Messaging\MessagingFailureCategory;
use App\Exceptions\Billing\EntitlementDeniedException;
use App\Exceptions\Messaging\MessagingException;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Billing\EntitlementResolver;

class OutboundMessageService
{
    public function __construct(
        private readonly MessagingProviderRegistry $providers,
        private readonly MessagingSessionWindowService $sessionWindow,
        private readonly MessagingEventRecorder $events,
        private readonly EntitlementResolver $entitlements,
    ) {}

    public function sendCounsellorReply(Conversation $conversation, Message $message): Message
    {
        $this->assertWhatsAppConversation($conversation);
        $this->assertSessionWindowOpen($conversation);

        return $this->dispatch($conversation, $message);
    }

    public function sendAssistantReply(Conversation $conversation, Message $message): Message
    {
        $this->assertWhatsAppConversation($conversation);
        $this->assertSessionWindowOpen($conversation);

        return $this->dispatch($conversation, $message);
    }

    private function dispatch(Conversation $conversation, Message $message): Message
    {
        $integration = $conversation->messagingIntegration;
        $contact = $conversation->messagingContact;

        if ($integration === null || $contact === null) {
            throw new MessagingException('WhatsApp integration is not linked to this conversation.', MessagingFailureCategory::ProviderUnavailable);
        }

        if (! $integration->isOperational()) {
            throw new MessagingException('WhatsApp integration is not operational.', MessagingFailureCategory::ProviderUnavailable);
        }

        try {
            $this->entitlements->assertAllowed($conversation->tenant, PlanFeature::WhatsAppIntegration);
        } catch (EntitlementDeniedException) {
            throw new MessagingException(
                MessagingFailureCategory::PermissionDenied->safeMessage(),
                MessagingFailureCategory::PermissionDenied,
            );
        }

        $provider = $this->providers->resolve($integration->provider);
        $recipient = $contact->external_contact_id;

        try {
            $result = $provider->sendTextMessage(
                $integration,
                new ProviderSendMessageRequest(
                    recipientPhone: $recipient,
                    body: (string) $message->body,
                    replyToProviderMessageId: $conversation->last_inbound_provider_message_id,
                ),
            );
        } catch (MessagingException $exception) {
            $message->update([
                'delivery_state' => MessageDeliveryState::Failed->value,
                'delivery_failure_category' => $exception->category->value,
            ]);

            $integration->update(['last_error_category' => $exception->category->value]);

            $this->events->record(
                MessagingEventType::ProviderFailure,
                $integration,
                $conversation,
                $message,
                metadata: ['category' => $exception->category->value],
            );

            throw $exception;
        }

        $message->update([
            'direction' => MessageDirection::Outbound->value,
            'provider_message_id' => $result->providerMessageId,
            'delivery_state' => MessageDeliveryState::Submitted->value,
        ]);

        $integration->update([
            'last_outbound_success_at' => now(),
            'last_error_category' => null,
        ]);

        $this->events->record(
            MessagingEventType::OutboundSubmitted,
            $integration,
            $conversation,
            $message,
            externalReference: $result->providerMessageId,
            metadata: $result->safeMetadata ?? [],
        );

        return $message->fresh();
    }

    public function updateDeliveryState(
        Conversation $conversation,
        string $providerMessageId,
        MessageDeliveryState $state,
        ?MessagingFailureCategory $failureCategory = null,
    ): void {
        $message = Message::query()
            ->where('tenant_id', $conversation->tenant_id)
            ->where('conversation_id', $conversation->id)
            ->where('provider_message_id', $providerMessageId)
            ->first();

        if ($message === null) {
            return;
        }

        $current = $message->delivery_state;

        if ($current instanceof MessageDeliveryState) {
            if ($state === MessageDeliveryState::Failed && $current->rank() >= MessageDeliveryState::Delivered->rank()) {
                return;
            }

            if ($current->rank() >= $state->rank() && $state !== MessageDeliveryState::Failed) {
                return;
            }
        }

        $message->update([
            'delivery_state' => $state->value,
            'delivery_failure_category' => $failureCategory?->value,
        ]);

        $this->events->record(
            MessagingEventType::DeliveryUpdated,
            $conversation->messagingIntegration,
            $conversation,
            $message,
            externalReference: $providerMessageId,
            metadata: ['state' => $state->value],
        );
    }

    private function assertWhatsAppConversation(Conversation $conversation): void
    {
        if ($conversation->channel !== ConversationChannel::WhatsApp) {
            throw new MessagingException('Conversation is not a WhatsApp channel.', MessagingFailureCategory::Unknown);
        }
    }

    private function assertSessionWindowOpen(Conversation $conversation): void
    {
        if (! $this->sessionWindow->isWithinWindowForConversation($conversation)) {
            $this->events->record(
                MessagingEventType::SessionWindowClosed,
                $conversation->messagingIntegration,
                $conversation,
            );

            throw new MessagingException(
                MessagingFailureCategory::SessionWindowClosed->safeMessage(),
                MessagingFailureCategory::SessionWindowClosed,
            );
        }
    }
}
