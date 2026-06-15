<?php

namespace App\Services\Messaging;

use App\Data\Messaging\ProviderTemplateSendRequest;
use App\Enums\Conversations\MessageRole;
use App\Enums\Messaging\MessageDeliveryState;
use App\Enums\Messaging\MessageDirection;
use App\Enums\Messaging\MessagingEventType;
use App\Enums\Messaging\MessagingFailureCategory;
use App\Exceptions\Messaging\MessagingException;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessagingTemplate;
use App\Models\TenantMessagingIntegration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TemplateMessageService
{
    public function __construct(
        private readonly MessagingProviderRegistry $providers,
        private readonly MessagingEventRecorder $events,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $components
     */
    public function sendTemplate(
        Conversation $conversation,
        string $templateName,
        string $languageCode = 'en',
        array $components = [],
        ?MessageRole $role = null,
    ): Message {
        $integration = $conversation->messagingIntegration;
        $contact = $conversation->messagingContact;

        if ($integration === null || $contact === null) {
            throw new MessagingException('WhatsApp integration is not linked to this conversation.', MessagingFailureCategory::ProviderUnavailable);
        }

        if (! $integration->isOperational()) {
            throw new MessagingException('WhatsApp integration is not operational.', MessagingFailureCategory::ProviderUnavailable);
        }

        $template = MessagingTemplate::query()
            ->where('messaging_integration_id', $integration->id)
            ->where('provider_template_name', $templateName)
            ->where('language_code', $languageCode)
            ->first();

        if ($template !== null && $template->status === 'rejected') {
            throw new MessagingException('Template was rejected by the provider.', MessagingFailureCategory::TemplateRejected);
        }

        $provider = $this->providers->resolve($integration->provider);

        $result = $provider->sendTemplateMessage(
            $integration,
            new ProviderTemplateSendRequest(
                recipientPhone: $contact->external_contact_id,
                templateName: $templateName,
                languageCode: $languageCode,
                components: $components,
            ),
        );

        return DB::transaction(function () use ($conversation, $integration, $templateName, $role, $result): Message {
            $message = Message::query()->create([
                'tenant_id' => $conversation->tenant_id,
                'conversation_id' => $conversation->id,
                'request_uuid' => (string) Str::uuid(),
                'role' => ($role ?? MessageRole::System)->value,
                'body' => '[template:'.$templateName.']',
                'direction' => MessageDirection::Outbound->value,
                'provider_message_id' => $result->providerMessageId,
                'delivery_state' => MessageDeliveryState::Submitted->value,
                'template_name' => $templateName,
            ]);

            $conversation->update(['last_message_at' => now()]);

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
                metadata: array_merge($result->safeMetadata ?? [], ['template_name' => $templateName]),
            );

            return $message;
        });
    }

    public function syncTemplate(
        TenantMessagingIntegration $integration,
        string $providerTemplateName,
        string $languageCode,
        string $status,
        ?string $category = null,
        ?array $variableDefinitions = null,
    ): MessagingTemplate {
        $template = MessagingTemplate::query()->updateOrCreate(
            [
                'messaging_integration_id' => $integration->id,
                'provider_template_name' => $providerTemplateName,
                'language_code' => $languageCode,
            ],
            [
                'tenant_id' => $integration->tenant_id,
                'category' => $category,
                'status' => $status,
                'variable_definitions' => $variableDefinitions,
                'last_synced_at' => now(),
            ],
        );

        $this->events->record(
            MessagingEventType::TemplateSynchronized,
            $integration,
            metadata: [
                'template_name' => $providerTemplateName,
                'language_code' => $languageCode,
                'status' => $status,
            ],
        );

        return $template;
    }
}
