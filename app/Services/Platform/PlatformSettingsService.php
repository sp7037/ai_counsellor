<?php

namespace App\Services\Platform;

use App\Enums\Audit\AuditAction;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class PlatformSettingsService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $settings = PlatformSetting::query()->pluck('value', 'key');

        return [
            'default_provider' => $settings['default_provider'] ?? config('ai.default_provider'),
            'allowed_models' => $settings['allowed_models'] ?? config('ai.allowed_models'),
            'default_fallback_message' => $settings['default_fallback_message'] ?? null,
            'support_email' => $settings['support_email'] ?? null,
            'platform_credential_configured' => $this->platformCredentialConfigured(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(array $input, User $actor): void
    {
        DB::transaction(function () use ($input, $actor): void {
            $this->upsert('default_provider', $input['default_provider'] ?? null, $actor);
            $this->upsert('allowed_models', $input['allowed_models'] ?? [], $actor);
            $this->upsert('default_fallback_message', $input['default_fallback_message'] ?? null, $actor);
            $this->upsert('support_email', $input['support_email'] ?? null, $actor);

            if (array_key_exists('platform_api_key', $input) && trim((string) $input['platform_api_key']) !== '') {
                $encrypted = Crypt::encryptString(trim((string) $input['platform_api_key']));
                $this->upsert('platform_openai_api_key', ['encrypted' => $encrypted], $actor);
                $this->auditLogger->log(
                    AuditAction::AiSecretReplaced,
                    null,
                    null,
                    ['scope' => 'platform', 'secret_masked' => '****'.substr(trim((string) $input['platform_api_key']), -4)],
                    $actor,
                );
            }

            $this->auditLogger->log(
                AuditAction::PlatformSettingsUpdated,
                null,
                null,
                ['keys' => array_keys(array_filter($input, fn ($value) => $value !== null))],
                $actor,
            );
        });
    }

    public function platformCredentialConfigured(): bool
    {
        $stored = PlatformSetting::query()->where('key', 'platform_openai_api_key')->value('value');

        if (is_array($stored) && ! empty($stored['encrypted'])) {
            return true;
        }

        return is_string(config('ai.providers.openai.api_key')) && config('ai.providers.openai.api_key') !== '';
    }

    private function upsert(string $key, mixed $value, User $actor): void
    {
        PlatformSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'updated_by' => $actor->id],
        );
    }
}
