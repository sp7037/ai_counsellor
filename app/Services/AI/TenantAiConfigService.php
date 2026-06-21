<?php

namespace App\Services\AI;

use App\Enums\AI\AiCredentialMode;
use App\Enums\AI\AiCredentialSource;
use App\Enums\Audit\AuditAction;
use App\Exceptions\AI\AiProviderException;
use App\Models\AiProvider;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\TenantAiConfig;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Str;

class TenantAiConfigService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return array{
     *     provider:string,
     *     model:string,
     *     temperature:float,
     *     max_output_tokens:int,
     *     timeout_seconds:int,
     *     api_key:?string,
     *     credential_mode:AiCredentialMode,
     *     credential_source:AiCredentialSource
     * }
     */
    public function getEffectiveConfig(Tenant $tenant): array
    {
        $config = TenantAiConfig::query()->with('provider')->first();
        $mode = $config?->credential_mode ?? AiCredentialMode::PlatformManaged;

        if ($config?->enabled && $config->provider?->enabled) {
            return $this->resolveEnabledTenantConfig($config, $mode);
        }

        if ($mode === AiCredentialMode::TenantKeyRequired) {
            throw new AiProviderException('Tenant provider API key is required for the selected credential mode.');
        }

        return $this->platformConfig($mode);
    }

    public function upsert(Tenant $tenant, array $input, User $actor): TenantAiConfig
    {
        $provider = AiProvider::query()->where('slug', $input['provider'])->firstOrFail();

        $config = $tenant->aiConfig()->firstOrNew([]);
        $config->tenant()->associate($tenant);

        $before = $this->snapshot($config);

        $config->provider()->associate($provider);
        $config->model = $this->validateModel($input['model']);
        $config->temperature = $this->boundTemperature((float) $input['temperature']);
        $config->max_output_tokens = $this->boundMaxOutputTokens((int) $input['max_output_tokens']);
        $config->timeout_seconds = $this->boundTimeout((int) $input['timeout_seconds']);
        $config->enabled = (bool) ($input['enabled'] ?? true);
        $config->credential_mode = isset($input['credential_mode'])
            ? ($input['credential_mode'] instanceof AiCredentialMode
                ? $input['credential_mode']
                : AiCredentialMode::from((string) $input['credential_mode']))
            : ($config->credential_mode ?? AiCredentialMode::PlatformManaged);
        $config->updated_by = $actor->id;

        if ($config->exists === false) {
            $config->created_by = $actor->id;
        }

        if (array_key_exists('api_key', $input)) {
            $key = trim((string) $input['api_key']);
            $config->encrypted_api_key = $key !== '' ? $key : null;
            $config->secret_updated_at = now();
        }

        if ($config->tenant_id !== $tenant->id) {
            throw new \RuntimeException('Tenant AI configuration must belong to the resolved tenant.');
        }

        $config->save();

        $this->auditLogger->log(
            AuditAction::AiConfigurationUpdated,
            $config,
            $tenant->id,
            [
                'before' => $before,
                'after' => $this->snapshot($config),
            ],
            $actor,
        );

        if (array_key_exists('api_key', $input)) {
            $this->auditLogger->log(
                AuditAction::AiSecretReplaced,
                $config,
                $tenant->id,
                [
                    'secret_masked' => $config->encrypted_api_key ? $this->mask($config->encrypted_api_key) : null,
                ],
                $actor,
            );
        }

        return $config->fresh(['provider']);
    }

    /**
     * @return array{
     *     provider:string,
     *     model:string,
     *     temperature:float,
     *     max_output_tokens:int,
     *     timeout_seconds:int,
     *     api_key:?string,
     *     credential_mode:AiCredentialMode,
     *     credential_source:AiCredentialSource
     * }
     */
    private function resolveEnabledTenantConfig(TenantAiConfig $config, AiCredentialMode $mode): array
    {
        $tenantKey = $config->encrypted_api_key;

        return match ($mode) {
            AiCredentialMode::TenantKeyRequired => $this->tenantOwnedConfig($config, $tenantKey, required: true),
            AiCredentialMode::PlatformManaged => $this->platformManagedConfig($config, $mode),
            AiCredentialMode::TenantKeyWithExplicitPlatformFallback => $tenantKey
                ? $this->tenantOwnedConfig($config, $tenantKey, required: false)
                : $this->platformManagedConfig($config, $mode),
        };
    }

    /**
     * @return array{
     *     provider:string,
     *     model:string,
     *     temperature:float,
     *     max_output_tokens:int,
     *     timeout_seconds:int,
     *     api_key:?string,
     *     credential_mode:AiCredentialMode,
     *     credential_source:AiCredentialSource
     * }
     */
    private function tenantOwnedConfig(TenantAiConfig $config, ?string $tenantKey, bool $required): array
    {
        if ($required && (! is_string($tenantKey) || trim($tenantKey) === '')) {
            throw new AiProviderException('Tenant provider API key is required for the selected credential mode.');
        }

        return [
            'provider' => $config->provider->slug,
            'model' => $this->validateModel($config->model),
            'temperature' => $this->boundTemperature((float) $config->temperature),
            'max_output_tokens' => $this->boundMaxOutputTokens((int) $config->max_output_tokens),
            'timeout_seconds' => $this->boundTimeout((int) $config->timeout_seconds),
            'api_key' => $tenantKey,
            'credential_mode' => $config->credential_mode,
            'credential_source' => AiCredentialSource::Tenant,
        ];
    }

    /**
     * @return array{
     *     provider:string,
     *     model:string,
     *     temperature:float,
     *     max_output_tokens:int,
     *     timeout_seconds:int,
     *     api_key:?string,
     *     credential_mode:AiCredentialMode,
     *     credential_source:AiCredentialSource
     * }
     */
    private function platformManagedConfig(TenantAiConfig $config, AiCredentialMode $mode): array
    {
        $slug = $config->provider->slug;
        $providerConfig = (array) config('ai.providers.'.$slug, []);

        if (($providerConfig['enabled'] ?? false) !== true || ! $config->provider->enabled) {
            throw new AiProviderException('AI provider is disabled.');
        }

        return [
            'provider' => $slug,
            'model' => $this->validateModel($config->model ?: (string) ($providerConfig['model'] ?? 'gpt-4o-mini')),
            'temperature' => $this->boundTemperature((float) $config->temperature),
            'max_output_tokens' => $this->boundMaxOutputTokens((int) $config->max_output_tokens),
            'timeout_seconds' => $this->boundTimeout((int) $config->timeout_seconds),
            'api_key' => null,
            'credential_mode' => $mode,
            'credential_source' => AiCredentialSource::Platform,
        ];
    }

    /**
     * @return array{
     *     provider:string,
     *     model:string,
     *     temperature:float,
     *     max_output_tokens:int,
     *     timeout_seconds:int,
     *     api_key:?string,
     *     credential_mode:AiCredentialMode,
     *     credential_source:AiCredentialSource
     * }
     */
    private function platformConfig(AiCredentialMode $mode): array
    {
        $provider = (string) (PlatformSetting::query()->where('key', 'default_provider')->value('value')
            ?? config('ai.default_provider', 'openai'));
        $providerConfig = (array) config('ai.providers.'.$provider, []);

        if (($providerConfig['enabled'] ?? false) !== true) {
            throw new AiProviderException('AI provider is disabled.');
        }

        return [
            'provider' => $provider,
            'model' => $this->validateModel((string) ($providerConfig['model'] ?? 'gpt-4o-mini')),
            'temperature' => $this->boundTemperature((float) ($providerConfig['temperature'] ?? 0.2)),
            'max_output_tokens' => $this->boundMaxOutputTokens((int) ($providerConfig['max_output_tokens'] ?? 400)),
            'timeout_seconds' => $this->boundTimeout((int) config('ai.request_timeout_seconds', 15)),
            'api_key' => null,
            'credential_mode' => $mode,
            'credential_source' => AiCredentialSource::Platform,
        ];
    }

    private function snapshot(TenantAiConfig $config): array
    {
        if (! $config->exists) {
            return [];
        }

        return [
            'provider' => $config->provider?->slug,
            'model' => $config->model,
            'temperature' => (float) $config->temperature,
            'max_output_tokens' => (int) $config->max_output_tokens,
            'timeout_seconds' => (int) $config->timeout_seconds,
            'enabled' => (bool) $config->enabled,
            'credential_mode' => $config->credential_mode?->value,
        ];
    }

    private function mask(string $secret): string
    {
        $last = Str::substr($secret, -4);

        return '****'.$last;
    }

    private function validateModel(string $model): string
    {
        $model = trim($model);
        $allowed = (array) config('ai.allowed_models', []);

        if ($allowed !== [] && ! in_array($model, $allowed, true)) {
            throw new AiProviderException('Selected model is not allowed.');
        }

        return $model;
    }

    private function boundTemperature(float $temperature): float
    {
        return max(
            (float) config('ai.min_temperature', 0.0),
            min((float) config('ai.max_temperature', 1.2), $temperature),
        );
    }

    private function boundMaxOutputTokens(int $tokens): int
    {
        return max(1, min((int) config('ai.max_output_tokens_limit', 1200), $tokens));
    }

    private function boundTimeout(int $seconds): int
    {
        return max(5, min(60, $seconds));
    }
}
