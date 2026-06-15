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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FakeMessagingProvider implements MessagingProviderContract
{
    public function __construct(
        private readonly MessagingCredentialService $credentials,
    ) {}

    public function provider(): MessagingProvider
    {
        return MessagingProvider::Fake;
    }

    public function verifyWebhookChallenge(
        string $mode,
        string $verifyToken,
        string $challenge,
        TenantMessagingIntegration $integration,
    ): ?string {
        if (! app()->environment('testing')) {
            return null;
        }

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
        if (! app()->environment('testing')) {
            return false;
        }

        $secret = $this->credentials->appSecret($integration)
            ?? config('messaging.providers.fake.app_secret');

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
        $this->assertTestingEnvironment();

        if (Http::getFacadeRoot() !== null) {
            $token = $this->credentials->accessToken($integration) ?? 'fake_token';

            $response = Http::baseUrl((string) config('messaging.providers.fake.base_url'))
                ->withToken((string) $token)
                ->acceptJson()
                ->post('/messages', [
                    'to' => MessagingConversationService::normalizePhone($request->recipientPhone),
                    'type' => 'text',
                    'text' => ['body' => $request->body],
                ]);

            if ($response->successful()) {
                $body = $response->json();
                if (is_array($body) && ! empty($body['messages'][0]['id'])) {
                    return new ProviderSendMessageResult(
                        providerMessageId: (string) $body['messages'][0]['id'],
                        status: 'submitted',
                    );
                }
            }

            if (in_array($response->status(), [401, 403, 429, 500, 502, 503], true)) {
                throw new MessagingException('Fake messaging provider HTTP failure.', MessagingFailureCategory::ProviderUnavailable);
            }
        }

        return new ProviderSendMessageResult(
            providerMessageId: 'wamid.'.Str::lower(Str::random(20)),
            status: 'submitted',
        );
    }

    public function sendTemplateMessage(
        TenantMessagingIntegration $integration,
        ProviderTemplateSendRequest $request,
    ): ProviderSendMessageResult {
        $this->assertTestingEnvironment();

        return new ProviderSendMessageResult(
            providerMessageId: 'wamid.'.Str::lower(Str::random(20)),
            status: 'submitted',
            safeMetadata: [
                'template_name' => $request->templateName,
            ],
        );
    }

    private function assertTestingEnvironment(): void
    {
        if (! app()->environment('testing')) {
            throw new MessagingException('Fake messaging provider is only available in testing.', MessagingFailureCategory::ProviderUnavailable);
        }
    }
}
