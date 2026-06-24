<?php

namespace App\Services\Platform;

use App\Enums\Audit\AuditAction;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\AI\PlatformAiCredentialResolver;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class PlatformSettingsService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly PlatformAiCredentialResolver $credentialResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $settings = PlatformSetting::query()->pluck('value', 'key');
        $defaultProvider = (string) ($settings['default_provider'] ?? config('ai.default_provider'));

        return [
            'default_provider' => $defaultProvider,
            'allowed_models' => $settings['allowed_models'] ?? config('ai.allowed_models'),
            'default_fallback_message' => $settings['default_fallback_message'] ?? null,
            'support_email' => $settings['support_email'] ?? null,
            'widget_powered_by_enabled' => (bool) ($settings['widget_powered_by_enabled'] ?? config('widget.powered_by.enabled', true)),
            'widget_powered_by_label' => (string) ($settings['widget_powered_by_label'] ?? config('widget.powered_by.label', 'Powered by SR Worlds AI')),
            'widget_powered_by_logo_url' => (string) ($settings['widget_powered_by_logo_url'] ?? ''),
            'widget_launcher_logo_url' => (string) ($settings['widget_launcher_logo_url'] ?? config('widget.launcher.logo_url', '')),
            'widget_launcher_teaser_text' => (string) ($settings['widget_launcher_teaser_text'] ?? config('widget.launcher.teaser_text', 'Ask AI Counsellor')),
            'widget_launcher_card_title' => (string) ($settings['widget_launcher_card_title'] ?? config('widget.launcher_card.title', '')),
            'widget_launcher_card_subtitle' => (string) ($settings['widget_launcher_card_subtitle'] ?? config('widget.launcher_card.subtitle', '')),
            'widget_launcher_card_cta_text' => (string) ($settings['widget_launcher_card_cta_text'] ?? config('widget.launcher_card.cta_text', '')),
            'widget_launcher_card_trust_text' => (string) ($settings['widget_launcher_card_trust_text'] ?? config('widget.launcher_card.trust_text', '')),
            'widget_launcher_card_delay_seconds' => (int) ($settings['widget_launcher_card_delay_seconds'] ?? config('widget.launcher_card.delay_seconds', 5)),
            'widget_launcher_card_dismiss_hours' => (int) ($settings['widget_launcher_card_dismiss_hours'] ?? config('widget.launcher_card.dismiss_reshow_seconds', 4)),
            'widget_launcher_card_animation' => (string) ($settings['widget_launcher_card_animation'] ?? config('widget.launcher_card.animation', 'soft_slide_up')),
            'platform_credential_configured' => $this->platformCredentialConfigured(),
            'openai_credential_configured' => $this->credentialResolver->isConfigured('openai'),
            'deepseek_credential_configured' => $this->credentialResolver->isConfigured('deepseek'),
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
            $this->upsert('widget_powered_by_enabled', (bool) ($input['widget_powered_by_enabled'] ?? config('widget.powered_by.enabled', true)), $actor);
            $this->upsert('widget_powered_by_label', (string) ($input['widget_powered_by_label'] ?? config('widget.powered_by.label', 'Powered by SR Worlds AI')), $actor);
            $this->upsert('widget_powered_by_logo_url', (string) ($input['widget_powered_by_logo_url'] ?? ''), $actor);
            $this->upsert('widget_launcher_logo_url', (string) ($input['widget_launcher_logo_url'] ?? ''), $actor);
            $this->upsert('widget_launcher_teaser_text', (string) ($input['widget_launcher_teaser_text'] ?? config('widget.launcher.teaser_text', 'Ask AI Counsellor')), $actor);
            $this->upsert('widget_launcher_card_title', (string) ($input['widget_launcher_card_title'] ?? ''), $actor);
            $this->upsert('widget_launcher_card_subtitle', (string) ($input['widget_launcher_card_subtitle'] ?? ''), $actor);
            $this->upsert('widget_launcher_card_cta_text', (string) ($input['widget_launcher_card_cta_text'] ?? ''), $actor);
            $this->upsert('widget_launcher_card_trust_text', (string) ($input['widget_launcher_card_trust_text'] ?? ''), $actor);
            $this->upsert('widget_launcher_card_delay_seconds', max(0, min(30, (int) ($input['widget_launcher_card_delay_seconds'] ?? config('widget.launcher_card.delay_seconds', 5)))), $actor);
            $this->upsert('widget_launcher_card_dismiss_hours', max(3, min(10, (int) ($input['widget_launcher_card_dismiss_hours'] ?? config('widget.launcher_card.dismiss_reshow_seconds', 4)))), $actor);
            $this->upsert('widget_launcher_card_animation', (string) ($input['widget_launcher_card_animation'] ?? config('widget.launcher_card.animation', 'soft_slide_up')), $actor);

            $this->storeProviderApiKey($input, 'platform_api_key', 'platform_openai_api_key', $actor);
            $this->storeProviderApiKey($input, 'platform_deepseek_api_key', 'platform_deepseek_api_key', $actor);

            $this->auditLogger->log(
                AuditAction::PlatformSettingsUpdated,
                null,
                null,
                ['keys' => array_keys(array_filter($input, fn ($value) => $value !== null))],
                $actor,
            );
        });
    }

    public function platformCredentialConfigured(?string $providerSlug = null): bool
    {
        if ($providerSlug !== null) {
            return $this->credentialResolver->isConfigured($providerSlug);
        }

        $defaultProvider = (string) (PlatformSetting::query()->where('key', 'default_provider')->value('value')
            ?? config('ai.default_provider', 'openai'));

        return $this->credentialResolver->isConfigured($defaultProvider);
    }

    private function storeProviderApiKey(array $input, string $inputKey, string $settingKey, User $actor): void
    {
        if (! array_key_exists($inputKey, $input) || trim((string) $input[$inputKey]) === '') {
            return;
        }

        $plain = trim((string) $input[$inputKey]);
        $encrypted = Crypt::encryptString($plain);
        $this->upsert($settingKey, ['encrypted' => $encrypted], $actor);
        $this->auditLogger->log(
            AuditAction::AiSecretReplaced,
            null,
            null,
            ['scope' => 'platform', 'provider' => str_replace('platform_', '', str_replace('_api_key', '', $settingKey)), 'secret_masked' => '****'.substr($plain, -4)],
            $actor,
        );
    }

    private function upsert(string $key, mixed $value, User $actor): void
    {
        PlatformSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'updated_by' => $actor->id],
        );
    }
}
