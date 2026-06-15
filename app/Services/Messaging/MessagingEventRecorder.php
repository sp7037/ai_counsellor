<?php

namespace App\Services\Messaging;

use App\Enums\Messaging\MessagingEventType;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessagingEvent;
use App\Models\TenantMessagingIntegration;

class MessagingEventRecorder
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        MessagingEventType $type,
        ?TenantMessagingIntegration $integration = null,
        ?Conversation $conversation = null,
        ?Message $message = null,
        ?string $externalReference = null,
        array $metadata = [],
        string $processingStatus = 'recorded',
    ): MessagingEvent {
        return MessagingEvent::query()->create([
            'tenant_id' => $integration?->tenant_id ?? $conversation?->tenant_id,
            'messaging_integration_id' => $integration?->id,
            'conversation_id' => $conversation?->id,
            'message_id' => $message?->id,
            'event_type' => $type->value,
            'external_reference' => $externalReference,
            'processing_status' => $processingStatus,
            'metadata' => $metadata === [] ? null : $metadata,
            'created_at' => now(),
        ]);
    }
}
