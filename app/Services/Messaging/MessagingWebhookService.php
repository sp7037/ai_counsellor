<?php

namespace App\Services\Messaging;

use App\Data\Messaging\InboundMessageData;
use App\Enums\Messaging\MessagingEventType;
use App\Enums\Messaging\MessagingFailureCategory;
use App\Enums\Tenancy\TenantStatus;
use App\Exceptions\Messaging\MessagingException;
use App\Models\MessagingWebhookEvent;
use App\Models\TenantMessagingIntegration;
use Illuminate\Support\Facades\Log;

class MessagingWebhookService
{
    public function __construct(
        private readonly MessagingProviderRegistry $providers,
        private readonly InboundMessageProcessor $processor,
        private readonly MessagingEventRecorder $events,
    ) {}

    public function verifyChallenge(
        string $mode,
        string $verifyToken,
        string $challenge,
    ): string {
        $integration = TenantMessagingIntegration::query()
            ->where('verify_token', $verifyToken)
            ->where('is_enabled', true)
            ->first();

        if ($integration === null) {
            throw new MessagingException('Unknown verify token.', MessagingFailureCategory::SignatureInvalid);
        }

        $provider = $this->providers->resolve($integration->provider);
        $response = $provider->verifyWebhookChallenge($mode, $verifyToken, $challenge, $integration);

        if ($response === null) {
            throw new MessagingException('Webhook verification failed.', MessagingFailureCategory::SignatureInvalid);
        }

        $this->events->record(
            MessagingEventType::WebhookVerified,
            $integration,
            metadata: ['mode' => $mode],
        );

        return $response;
    }

    public function handlePost(
        string $rawBody,
        ?string $signature,
    ): array {
        if ($signature === null || trim($signature) === '') {
            throw new MessagingException('Missing webhook signature.', MessagingFailureCategory::SignatureInvalid);
        }

        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            throw new MessagingException('Invalid webhook payload.', MessagingFailureCategory::Unknown);
        }

        $integration = $this->resolveIntegrationFromPayload($payload);

        if ($integration === null) {
            return ['status' => 'ignored', 'reason' => 'unknown_integration'];
        }

        $messagingProvider = $this->providers->resolve($integration->provider);

        if (! $messagingProvider->verifyWebhookSignature($rawBody, $signature, $integration)) {
            throw new MessagingException('Invalid webhook signature.', MessagingFailureCategory::SignatureInvalid);
        }

        $provider = $integration->provider;

        $this->events->record(
            MessagingEventType::WebhookVerified,
            $integration,
            metadata: ['provider' => $provider->value],
        );

        $eventId = $this->extractProviderEventId($payload, $rawBody);
        $eventType = $this->extractEventType($payload);

        $existing = MessagingWebhookEvent::query()
            ->where('provider', $provider->value)
            ->where('provider_event_id', $eventId)
            ->first();

        if ($existing !== null) {
            return ['status' => 'duplicate', 'event_id' => $eventId];
        }

        $webhookEvent = MessagingWebhookEvent::query()->create([
            'provider' => $provider->value,
            'provider_event_id' => $eventId,
            'event_type' => $eventType,
            'status' => 'received',
            'metadata' => $this->redactPayload($payload),
            'created_at' => now(),
        ]);

        try {
            $result = $this->processPayload($integration, $payload);
            $integration->update(['last_webhook_at' => now()]);

            $webhookEvent->update([
                'status' => 'processed',
                'processed_at' => now(),
            ]);

            $status = ($result['handled'] ?? true) === false
                ? (string) ($result['status'] ?? 'ignored')
                : 'processed';

            return array_merge(['status' => $status, 'event_id' => $eventId], $result);
        } catch (\Throwable $exception) {
            Log::warning('Messaging webhook processing failed', [
                'event_id' => $eventId,
                'event_type' => $eventType,
                'message' => $exception->getMessage(),
            ]);

            $webhookEvent->update([
                'status' => 'failed',
                'processed_at' => now(),
                'metadata' => array_merge($webhookEvent->metadata ?? [], [
                    'error' => $exception->getMessage(),
                ]),
            ]);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function processPayload(TenantMessagingIntegration $integration, array $payload): array
    {
        if ($integration->tenant->status !== TenantStatus::Active) {
            return ['handled' => false, 'status' => 'tenant_inactive'];
        }

        if (! $integration->is_enabled) {
            return ['handled' => false, 'status' => 'integration_disabled'];
        }

        $results = [];

        foreach ($payload['entry'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            foreach ($entry['changes'] ?? [] as $change) {
                if (! is_array($change)) {
                    continue;
                }

                $value = $change['value'] ?? null;
                if (! is_array($value)) {
                    continue;
                }

                foreach ($value['messages'] ?? [] as $message) {
                    if (! is_array($message)) {
                        continue;
                    }

                    $inbound = $this->mapInboundMessage($message, $value);
                    if ($inbound !== null) {
                        $results[] = $this->processor->process($integration, $inbound);
                    }
                }

                foreach ($value['statuses'] ?? [] as $status) {
                    if (! is_array($status)) {
                        continue;
                    }

                    $results[] = $this->processor->processDeliveryStatus(
                        $integration,
                        (string) ($status['id'] ?? ''),
                        (string) ($status['status'] ?? ''),
                        isset($status['recipient_id']) ? (string) $status['recipient_id'] : null,
                    );
                }
            }
        }

        return ['handled' => $results !== [], 'results' => $results];
    }

    /**
     * @param  array<string, mixed>  $message
     * @param  array<string, mixed>  $value
     */
    private function mapInboundMessage(array $message, array $value): ?InboundMessageData
    {
        $providerMessageId = (string) ($message['id'] ?? '');
        $senderPhone = (string) ($message['from'] ?? '');

        if ($providerMessageId === '' || $senderPhone === '') {
            return null;
        }

        $type = (string) ($message['type'] ?? 'text');
        $body = match ($type) {
            'text' => (string) ($message['text']['body'] ?? ''),
            'button' => (string) ($message['button']['text'] ?? $message['button']['payload'] ?? ''),
            'interactive' => (string) ($message['interactive']['button_reply']['title']
                ?? $message['interactive']['list_reply']['title']
                ?? ''),
            default => '',
        };

        if ($body === '') {
            return null;
        }

        $senderName = null;
        foreach ($value['contacts'] ?? [] as $contact) {
            if (! is_array($contact)) {
                continue;
            }

            if ((string) ($contact['wa_id'] ?? '') === MessagingConversationService::normalizePhone($senderPhone)) {
                $senderName = $contact['profile']['name'] ?? null;
                break;
            }
        }

        return new InboundMessageData(
            providerMessageId: $providerMessageId,
            senderPhone: $senderPhone,
            body: $body,
            senderName: is_string($senderName) ? $senderName : null,
            replyToProviderMessageId: isset($message['context']['id']) ? (string) $message['context']['id'] : null,
            messageType: $type,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveIntegrationFromPayload(array $payload): ?TenantMessagingIntegration
    {
        foreach ($payload['entry'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            foreach ($entry['changes'] ?? [] as $change) {
                if (! is_array($change)) {
                    continue;
                }

                $phoneNumberId = $change['value']['metadata']['phone_number_id'] ?? null;

                if (! is_string($phoneNumberId) || $phoneNumberId === '') {
                    continue;
                }

                return TenantMessagingIntegration::query()
                    ->where('phone_number_id', $phoneNumberId)
                    ->first();
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractProviderEventId(array $payload, string $rawBody): string
    {
        foreach ($payload['entry'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            foreach ($entry['changes'] ?? [] as $change) {
                if (! is_array($change)) {
                    continue;
                }

                $value = $change['value'] ?? null;
                if (! is_array($value)) {
                    continue;
                }

                foreach ($value['messages'] ?? [] as $message) {
                    if (is_array($message) && ! empty($message['id'])) {
                        return (string) $message['id'];
                    }
                }

                foreach ($value['statuses'] ?? [] as $status) {
                    if (is_array($status) && ! empty($status['id'])) {
                        return (string) $status['id'];
                    }
                }
            }
        }

        return hash('sha256', $rawBody);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractEventType(array $payload): string
    {
        foreach ($payload['entry'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            foreach ($entry['changes'] ?? [] as $change) {
                if (is_array($change) && isset($change['field'])) {
                    return (string) $change['field'];
                }
            }
        }

        return (string) ($payload['object'] ?? 'unknown');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function redactPayload(array $payload): array
    {
        return [
            'object' => $payload['object'] ?? null,
            'entry_count' => count($payload['entry'] ?? []),
        ];
    }
}
