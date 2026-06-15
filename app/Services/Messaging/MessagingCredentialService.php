<?php

namespace App\Services\Messaging;

use App\Models\TenantMessagingIntegration;
use Illuminate\Support\Facades\Crypt;

class MessagingCredentialService
{
    public function accessToken(TenantMessagingIntegration $integration): ?string
    {
        return $this->decryptStored($integration->access_token);
    }

    public function appSecret(TenantMessagingIntegration $integration): ?string
    {
        return $this->decryptStored($integration->app_secret);
    }

    public function accessTokenConfigured(TenantMessagingIntegration $integration): bool
    {
        $token = $this->accessToken($integration);

        return is_string($token) && trim($token) !== '';
    }

    public function appSecretConfigured(TenantMessagingIntegration $integration): bool
    {
        $secret = $this->appSecret($integration);

        return is_string($secret) && trim($secret) !== '';
    }

    public function storeAccessToken(TenantMessagingIntegration $integration, string $token): void
    {
        $integration->update([
            'access_token' => ['encrypted' => Crypt::encryptString(trim($token))],
        ]);
    }

    public function storeAppSecret(TenantMessagingIntegration $integration, string $secret): void
    {
        $integration->update([
            'app_secret' => ['encrypted' => Crypt::encryptString(trim($secret))],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function safeSummary(TenantMessagingIntegration $integration): array
    {
        return [
            'provider' => $integration->provider->value,
            'environment' => $integration->environment,
            'status' => $integration->status->value,
            'phone_number_id' => $integration->phone_number_id,
            'display_phone_number' => $integration->display_phone_number,
            'business_display_name' => $integration->business_display_name,
            'is_enabled' => $integration->is_enabled,
            'access_token_configured' => $this->accessTokenConfigured($integration),
            'app_secret_configured' => $this->appSecretConfigured($integration),
            'last_webhook_at' => $integration->last_webhook_at?->toIso8601String(),
            'last_outbound_success_at' => $integration->last_outbound_success_at?->toIso8601String(),
            'last_error_category' => $integration->last_error_category,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $stored
     */
    private function decryptStored(?array $stored): ?string
    {
        if (! is_array($stored) || empty($stored['encrypted'])) {
            return null;
        }

        try {
            return Crypt::decryptString($stored['encrypted']);
        } catch (\Throwable) {
            return null;
        }
    }
}
