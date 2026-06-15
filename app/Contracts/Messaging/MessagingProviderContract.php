<?php

namespace App\Contracts\Messaging;

use App\Data\Messaging\ProviderSendMessageRequest;
use App\Data\Messaging\ProviderSendMessageResult;
use App\Data\Messaging\ProviderTemplateSendRequest;
use App\Enums\Messaging\MessagingProvider;
use App\Models\TenantMessagingIntegration;

interface MessagingProviderContract
{
    public function provider(): MessagingProvider;

    public function verifyWebhookChallenge(
        string $mode,
        string $verifyToken,
        string $challenge,
        TenantMessagingIntegration $integration,
    ): ?string;

    public function verifyWebhookSignature(
        string $rawBody,
        string $signature,
        TenantMessagingIntegration $integration,
    ): bool;

    public function sendTextMessage(
        TenantMessagingIntegration $integration,
        ProviderSendMessageRequest $request,
    ): ProviderSendMessageResult;

    public function sendTemplateMessage(
        TenantMessagingIntegration $integration,
        ProviderTemplateSendRequest $request,
    ): ProviderSendMessageResult;
}
