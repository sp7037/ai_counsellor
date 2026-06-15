<?php

namespace App\Services\Messaging;

use App\Data\Messaging\InboundMessageData;
use App\Enums\Conversations\ConversationMode;
use App\Enums\Conversations\MessageRole;
use App\Enums\Leads\LeadSource;
use App\Enums\Messaging\MessageDeliveryState;
use App\Enums\Messaging\MessageDirection;
use App\Enums\Messaging\MessagingEventType;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\TenantMessagingIntegration;
use App\Services\Conversations\ConversationNotificationService;
use App\Services\Conversations\ConversationReadStateService;
use App\Services\Leads\LeadCreationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InboundMessageProcessor
{
    public function __construct(
        private readonly MessagingConversationService $conversations,
        private readonly MessagingEventRecorder $events,
        private readonly MessagingAiResponseService $aiResponses,
        private readonly ConversationReadStateService $readState,
        private readonly ConversationNotificationService $notifications,
        private readonly OutboundMessageService $outbound,
        private readonly LeadCreationService $leadCreation,
    ) {}

    public function process(
        TenantMessagingIntegration $integration,
        InboundMessageData $inbound,
    ): array {
        $existing = Message::query()
            ->where('tenant_id', $integration->tenant_id)
            ->where('provider_message_id', $inbound->providerMessageId)
            ->first();

        if ($existing !== null) {
            $this->events->record(
                MessagingEventType::DuplicateIgnored,
                $integration,
                $existing->conversation,
                $existing,
                externalReference: $inbound->providerMessageId,
            );

            return ['status' => 'duplicate', 'message_id' => $existing->uuid];
        }

        $contact = $this->conversations->findOrCreateContact(
            $integration,
            $inbound->senderPhone,
            $inbound->senderName,
        );

        $resolved = $this->conversations->findOrCreateConversation($integration, $contact);
        $conversation = $resolved['conversation'];
        $createdConversation = $resolved['created'];

        $message = DB::transaction(function () use ($integration, $inbound, $contact, $conversation): Message {
            $contact->update([
                'last_inbound_at' => now(),
                'display_name' => $inbound->senderName ?? $contact->display_name,
            ]);

            $message = Message::query()->create([
                'tenant_id' => $integration->tenant_id,
                'conversation_id' => $conversation->id,
                'request_uuid' => (string) Str::uuid(),
                'role' => MessageRole::Visitor->value,
                'body' => $inbound->body,
                'direction' => MessageDirection::Inbound->value,
                'provider_message_id' => $inbound->providerMessageId,
                'delivery_state' => MessageDeliveryState::Delivered->value,
                'reply_to_provider_message_id' => $inbound->replyToProviderMessageId,
            ]);

            $conversation->update([
                'last_message_at' => now(),
                'last_visitor_message_at' => now(),
                'last_inbound_provider_message_id' => $inbound->providerMessageId,
                'external_channel_reference' => $contact->external_contact_id,
            ]);

            $this->events->record(
                MessagingEventType::InboundAccepted,
                $integration,
                $conversation,
                $message,
                externalReference: $inbound->providerMessageId,
            );

            return $message;
        });

        if ($createdConversation && $conversation->lead_id === null) {
            try {
                $lead = $this->leadCreation->create(
                    $integration->tenant,
                    LeadSource::WhatsApp,
                    [
                        'conversation_id' => $conversation->id,
                        'full_name' => $inbound->senderName ?? 'WhatsApp contact',
                        'mobile' => $inbound->senderPhone,
                        'enquiry_summary' => Str::limit($inbound->body, 500),
                    ],
                    sourceReference: $inbound->providerMessageId,
                );
                $conversation->refresh();
            } catch (\Throwable) {
                // Lead creation is optional when lead management is unavailable.
            }
        }

        if ($conversation->mode === ConversationMode::Ai) {
            try {
                $this->aiResponses->respondToInbound($conversation, $message, $inbound->body);
            } catch (\Throwable) {
                // Inbound storage must succeed even when AI generation or delivery fails.
            }
        } else {
            DB::transaction(function () use ($conversation): void {
                $this->readState->incrementCounsellorUnread($conversation);
            });

            if ($conversation->human_owner_id !== null) {
                $this->notifications->newVisitorMessage($conversation, $conversation->human_owner_id);
            }
        }

        return [
            'status' => 'processed',
            'message_id' => $message->uuid,
            'conversation_id' => $conversation->uuid,
            'conversation_created' => $createdConversation,
        ];
    }

    public function processDeliveryStatus(
        TenantMessagingIntegration $integration,
        string $providerMessageId,
        string $status,
        ?string $recipientPhone = null,
    ): array {
        $conversation = $this->resolveConversationForStatus($integration, $providerMessageId, $recipientPhone);

        if ($conversation === null) {
            return ['status' => 'ignored', 'reason' => 'conversation_not_found'];
        }

        $deliveryState = match ($status) {
            'sent' => MessageDeliveryState::Sent,
            'delivered' => MessageDeliveryState::Delivered,
            'read' => MessageDeliveryState::Read,
            'failed' => MessageDeliveryState::Failed,
            default => null,
        };

        if ($deliveryState === null) {
            return ['status' => 'ignored', 'reason' => 'unsupported_status'];
        }

        $this->outbound->updateDeliveryState($conversation, $providerMessageId, $deliveryState);

        return ['status' => 'processed', 'delivery_state' => $deliveryState->value];
    }

    private function resolveConversationForStatus(
        TenantMessagingIntegration $integration,
        string $providerMessageId,
        ?string $recipientPhone,
    ): ?Conversation {
        $message = Message::query()
            ->where('tenant_id', $integration->tenant_id)
            ->where('provider_message_id', $providerMessageId)
            ->first();

        if ($message !== null) {
            return $message->conversation;
        }

        if ($recipientPhone === null) {
            return null;
        }

        $normalized = MessagingConversationService::normalizePhone($recipientPhone);
        $contact = $integration->contacts()
            ->where('external_contact_id', $normalized)
            ->first();

        if ($contact === null) {
            return null;
        }

        return Conversation::query()
            ->where('tenant_id', $integration->tenant_id)
            ->where('messaging_contact_id', $contact->id)
            ->latest('id')
            ->first();
    }
}
