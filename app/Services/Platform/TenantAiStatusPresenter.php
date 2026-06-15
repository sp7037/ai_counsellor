<?php

namespace App\Services\Platform;

use App\Enums\AI\AiCredentialMode;
use App\Models\TenantAiConfig;

class TenantAiStatusPresenter
{
    /**
     * @return array{label:string,variant:string,detail:string}
     */
    public function summarize(?TenantAiConfig $config): array
    {
        if ($config === null || ! $config->enabled) {
            return [
                'label' => 'AI disabled',
                'variant' => 'zinc',
                'detail' => 'Tenant AI replies are disabled or not configured.',
            ];
        }

        $mode = $config->credential_mode ?? AiCredentialMode::PlatformManaged;
        $hasKey = is_string($config->encrypted_api_key) && trim($config->encrypted_api_key) !== '';

        return match ($mode) {
            AiCredentialMode::TenantKeyRequired => $hasKey
                ? ['label' => 'Tenant key configured', 'variant' => 'green', 'detail' => 'Tenant-owned credential is present (value not shown).']
                : ['label' => 'Missing required credential', 'variant' => 'red', 'detail' => 'Tenant key is required but not configured.'],
            AiCredentialMode::PlatformManaged => [
                'label' => 'Platform managed',
                'variant' => 'blue',
                'detail' => 'Runs use the platform-managed credential path.',
            ],
            AiCredentialMode::TenantKeyWithExplicitPlatformFallback => $hasKey
                ? ['label' => 'Tenant key configured', 'variant' => 'green', 'detail' => 'Tenant key present; explicit platform fallback enabled if removed.']
                : ['label' => 'Explicit platform fallback', 'variant' => 'amber', 'detail' => 'No tenant key; explicit platform fallback may be used.'],
        };
    }

    public function credentialModeLabel(?TenantAiConfig $config): string
    {
        if ($config === null) {
            return 'Not configured';
        }

        return ($config->credential_mode ?? AiCredentialMode::PlatformManaged)->label();
    }
}
