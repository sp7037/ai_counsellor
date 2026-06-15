<?php

namespace App\Services\Messaging\Providers;

use App\Contracts\Messaging\MessagingProviderContract;
use App\Data\Messaging\ProviderSendMessageRequest;
use App\Data\Messaging\ProviderSendMessageResult;
use App\Data\Messaging\ProviderTemplateSendRequest;
use App\Enums\Messaging\MessagingFailureCategory;
use App\Enums\Messaging\MessagingProvider;
use App\Exceptions\Messaging\MessagingException;
use App\Models\TenantMessagingIntegration;
use App\Services\Messaging\MessagingConversationService;
use App\Services\Messaging\MessagingCredentialService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaWhatsAppCloudProvider implements MessagingProviderContract
{
    public function __construct(
        private readonly MessagingCredentialService $credentials,
    ) {}

    public function provider(): MessagingProvider
    {
        return MessagingProvider::Meta;
    }

    public function verifyWebhookChallenge(
        string $mode,
        string $verifyToken,
        string $challenge,
        TenantMessagingIntegration $integration,
    ): ?string {
        if ($mode !== 'subscribe' || ! hash_equals($integration->verify_token, $verifyToken)) {
            return null;
        }

        return $challenge;
    }

    public function verifyWebhookSignature(
        string $rawBody,
        string $signature,
        TenantMessagingIntegration $integration,
    ): bool {
        $secret = $this->credentials->appSecret($integration);

        if (! is_string($secret) || $secret === '') {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $signature);
    }

    public function sendTextMessage(
        TenantMessagingIntegration $integration,
        ProviderSendMessageRequest $request,
    ): ProviderSendMessageResult {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => MessagingConversationService::normalizePhone($request->recipientPhone),
            'type' => 'text',
            'text' => ['body' => $request->body],
        ];

        if ($request->replyToProviderMessageId !== null) {
            $payload['context'] = ['message_id' => $request->replyToProviderMessageId];
        }

        return $this->send($integration, $payload);
    }

    public function sendTemplateMessage(
        TenantMessagingIntegration $integration,
        ProviderTemplateSendRequest $request,
    ): ProviderSendMessageResult {
        $template = [
            'name' => $request->templateName,
            'language' => ['code' => $request->languageCode],
        ];

        if ($request->components !== []) {
            $template['components'] = $request->components;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => MessagingConversationService::normalizePhone($request->recipientPhone),
            'type' => 'template',
            'template' => $template,
        ];

        return $this->send($integration, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(TenantMessagingIntegration $integration, array $payload): ProviderSendMessageResult
    {
        $phoneNumberId = $integration->phone_number_id;

        if (! is_string($phoneNumberId) || $phoneNumberId === '') {
            throw new MessagingException('WhatsApp phone number ID is not configured.', MessagingFailureCategory::ProviderUnavailable);
        }

        $token = $this->credentials->accessToken($integration);

        if (! is_string($token) || $token === '') {
            throw new MessagingException('WhatsApp access token is not configured.', MessagingFailureCategory::AuthenticationFailed);
        }

        try {
            $response = Http::baseUrl((string) config('messaging.providers.meta.base_url'))
                ->withToken($token)
                ->timeout((int) config('messaging.request_timeout_seconds', 15))
                ->connectTimeout((int) config('messaging.connect_timeout_seconds', 5))
                ->retry((int) config('messaging.http_retries', 0), 0, throw: false)
                ->acceptJson()
                ->post('/'.$phoneNumberId.'/messages', $payload);
        } catch (ConnectionException) {
            throw new MessagingException('WhatsApp request timed out.', MessagingFailureCategory::Timeout);
        }

        if (in_array($response->status(), [401, 403], true)) {
            Log::warning('WhatsApp authentication failed', ['status' => $response->status()]);

            throw new MessagingException('WhatsApp authentication failed.', MessagingFailureCategory::AuthenticationFailed);
        }

        if ($response->status() === 429) {
            throw new MessagingException('WhatsApp rate limit reached.', MessagingFailureCategory::RateLimited);
        }

        if (! $response->successful()) {
            Log::warning('WhatsApp message send failed', ['status' => $response->status()]);

            throw new MessagingException('WhatsApp message send failed.', MessagingFailureCategory::ProviderUnavailable);
        }

        $body = $response->json();

        if (! is_array($body) || empty($body['messages'][0]['id'])) {
            throw new MessagingException('WhatsApp returned an invalid response.', MessagingFailureCategory::MalformedResponse);
        }

        return new ProviderSendMessageResult(
            providerMessageId: (string) $body['messages'][0]['id'],
            status: 'submitted',
            safeMetadata: [
                'messaging_product' => $body['messaging_product'] ?? null,
            ],
        );
    }
}
