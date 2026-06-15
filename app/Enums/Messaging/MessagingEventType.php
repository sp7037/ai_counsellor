<?php

namespace App\Enums\Messaging;

enum MessagingEventType: string
{
    case WebhookVerified = 'webhook_verified';
    case InboundAccepted = 'inbound_accepted';
    case DuplicateIgnored = 'duplicate_ignored';
    case ConversationCreated = 'conversation_created';
    case AiGenerationRequested = 'ai_generation_requested';
    case OutboundSubmitted = 'outbound_submitted';
    case DeliveryUpdated = 'delivery_updated';
    case ProviderFailure = 'provider_failure';
    case CredentialReplaced = 'credential_replaced';
    case IntegrationEnabled = 'integration_enabled';
    case IntegrationDisabled = 'integration_disabled';
    case TemplateSynchronized = 'template_synchronized';
    case SessionWindowClosed = 'session_window_closed';

    public function label(): string
    {
        return match ($this) {
            self::WebhookVerified => 'Webhook verified',
            self::InboundAccepted => 'Inbound message accepted',
            self::DuplicateIgnored => 'Duplicate ignored',
            self::ConversationCreated => 'Conversation created',
            self::AiGenerationRequested => 'AI generation requested',
            self::OutboundSubmitted => 'Outbound message submitted',
            self::DeliveryUpdated => 'Delivery updated',
            self::ProviderFailure => 'Provider failure',
            self::CredentialReplaced => 'Credential replaced',
            self::IntegrationEnabled => 'Integration enabled',
            self::IntegrationDisabled => 'Integration disabled',
            self::TemplateSynchronized => 'Template synchronized',
            self::SessionWindowClosed => 'Session window closed',
        };
    }
}
