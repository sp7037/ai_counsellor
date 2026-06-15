<?php

namespace App\Services\AI;

use App\Enums\Audit\AuditAction;
use App\Exceptions\AI\AiProviderException;
use App\Models\AiProvider;
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

    public function getEffectiveConfig(Tenant $tenant): array
    {
        $config = TenantAiConfig::query()->first();

        if ($config?->enabled && $config->provider?->enabled) {
            return [
                'provider' => $config->provider->slug,
                'model' => $config->model,
                'temperature' => (float) $config->temperature,
                'max_output_tokens' => (int) $config->max_output_tokens,
                'timeout_seconds' => (int) $config->timeout_seconds,
                'api_key' => $config->encrypted_api_key,
            ];
        }

        $provider = (string) config('ai.default_provider', 'openai');
        $providerConfig = (array) config('ai.providers.'.$provider, []);

        if (($providerConfig['enabled'] ?? false) !== true) {
            throw new AiProviderException('AI provider is disabled.');
        }

        return [
            'provider' => $provider,
            'model' => (string) ($providerConfig['model'] ?? 'gpt-4o-mini'),
            'temperature' => (float) ($providerConfig['temperature'] ?? 0.2),
            'max_output_tokens' => (int) ($providerConfig['max_output_tokens'] ?? 400),
            'timeout_seconds' => (int) config('ai.request_timeout_seconds', 15),
            'api_key' => null,
        ];
    }

    public function upsert(Tenant $tenant, array $input, User $actor): TenantAiConfig
    {
        $provider = AiProvider::query()->where('slug', $input['provider'])->firstOrFail();

        $config = TenantAiConfig::query()->firstOrNew([
            'tenant_id' => $tenant->id,
        ]);

        $before = $this->snapshot($config);

        $config->provider()->associate($provider);
        $config->model = $input['model'];
        $config->temperature = $input['temperature'];
        $config->max_output_tokens = $input['max_output_tokens'];
        $config->timeout_seconds = $input['timeout_seconds'];
        $config->enabled = (bool) ($input['enabled'] ?? true);
        $config->updated_by = $actor->id;

        if ($config->exists === false) {
            $config->created_by = $actor->id;
        }

        if (array_key_exists('api_key', $input)) {
            $key = trim((string) $input['api_key']);
            $config->encrypted_api_key = $key !== '' ? $key : null;
            $config->secret_updated_at = now();
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
        ];
    }

    private function mask(string $secret): string
    {
        $last = Str::substr($secret, -4);

        return '****'.$last;
    }
}
