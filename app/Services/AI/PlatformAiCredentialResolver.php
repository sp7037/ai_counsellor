<?php

namespace App\Services\AI;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Crypt;

class PlatformAiCredentialResolver
{
    public function resolve(string $providerSlug): ?string
    {
        $settingKey = match ($providerSlug) {
            'openai' => 'platform_openai_api_key',
            'deepseek' => 'platform_deepseek_api_key',
            default => null,
        };

        if ($settingKey !== null) {
            $stored = PlatformSetting::query()->where('key', $settingKey)->value('value');

            if (is_array($stored) && ! empty($stored['encrypted'])) {
                try {
                    return Crypt::decryptString($stored['encrypted']);
                } catch (\Throwable) {
                    return null;
                }
            }
        }

        $envKey = config("ai.providers.{$providerSlug}.api_key");

        return is_string($envKey) && trim($envKey) !== '' ? $envKey : null;
    }

    public function isConfigured(string $providerSlug): bool
    {
        return $this->resolve($providerSlug) !== null;
    }
}
