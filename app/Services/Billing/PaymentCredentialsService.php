<?php

namespace App\Services\Billing;

use App\Enums\Audit\AuditAction;
use App\Enums\Billing\PaymentEnvironment;
use App\Enums\Billing\PaymentProvider;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Crypt;

class PaymentCredentialsService
{
    public function environment(): PaymentEnvironment
    {
        $stored = PlatformSetting::query()->where('key', 'payment_environment')->value('value');

        if (is_string($stored) && $stored !== '') {
            return PaymentEnvironment::from($stored);
        }

        return PaymentEnvironment::from((string) config('payments.environment', 'test'));
    }

    public function isEnabled(): bool
    {
        $stored = PlatformSetting::query()->where('key', 'payment_enabled')->value('value');

        if ($stored !== null) {
            return (bool) $stored;
        }

        if (app()->environment('testing', 'local')) {
            return (bool) config('payments.providers.fake.enabled', true);
        }

        return (bool) config('payments.providers.razorpay.enabled', false);
    }

    public function activeProvider(): PaymentProvider
    {
        if (app()->environment('testing')) {
            return PaymentProvider::Fake;
        }

        $stored = PlatformSetting::query()->where('key', 'payment_provider')->value('value');

        if (is_string($stored) && $stored !== '') {
            return PaymentProvider::from($stored);
        }

        return PaymentProvider::from((string) config('payments.default_provider', 'razorpay'));
    }

    public function keyId(?PaymentProvider $provider = null, ?PaymentEnvironment $environment = null): ?string
    {
        $provider ??= $this->activeProvider();
        $environment ??= $this->environment();

        $stored = $this->storedCredential($provider, $environment, 'key_id');
        if ($stored !== null) {
            return $stored;
        }

        $configKey = $provider === PaymentProvider::Fake ? 'fake' : 'razorpay';

        return config("payments.providers.{$configKey}.key_id");
    }

    public function keySecret(?PaymentProvider $provider = null, ?PaymentEnvironment $environment = null): ?string
    {
        $provider ??= $this->activeProvider();
        $environment ??= $this->environment();

        $encrypted = $this->storedEncrypted($provider, $environment, 'key_secret');
        if ($encrypted !== null) {
            return $encrypted;
        }

        $configKey = $provider === PaymentProvider::Fake ? 'fake' : 'razorpay';

        return config("payments.providers.{$configKey}.key_secret");
    }

    public function webhookSecret(?PaymentProvider $provider = null, ?PaymentEnvironment $environment = null): ?string
    {
        $provider ??= $this->activeProvider();
        $environment ??= $this->environment();

        $encrypted = $this->storedEncrypted($provider, $environment, 'webhook_secret');
        if ($encrypted !== null) {
            return $encrypted;
        }

        $configKey = $provider === PaymentProvider::Fake ? 'fake' : 'razorpay';

        return config("payments.providers.{$configKey}.webhook_secret");
    }

    public function keySecretConfigured(?PaymentProvider $provider = null, ?PaymentEnvironment $environment = null): bool
    {
        $secret = $this->keySecret($provider, $environment);

        return is_string($secret) && trim($secret) !== '';
    }

    public function webhookSecretConfigured(?PaymentProvider $provider = null, ?PaymentEnvironment $environment = null): bool
    {
        $secret = $this->webhookSecret($provider, $environment);

        return is_string($secret) && trim($secret) !== '';
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function updateSettings(array $input, User $actor, AuditLogger $audit): void
    {
        if (array_key_exists('payment_enabled', $input)) {
            $this->upsert('payment_enabled', (bool) $input['payment_enabled'], $actor);
        }

        if (array_key_exists('payment_environment', $input) && $input['payment_environment'] !== null) {
            $this->upsert('payment_environment', $input['payment_environment'], $actor);
        }

        if (array_key_exists('payment_provider', $input) && $input['payment_provider'] !== null) {
            $this->upsert('payment_provider', $input['payment_provider'], $actor);
        }

        $provider = isset($input['payment_provider'])
            ? PaymentProvider::from($input['payment_provider'])
            : $this->activeProvider();
        $environment = isset($input['payment_environment'])
            ? PaymentEnvironment::from($input['payment_environment'])
            : $this->environment();

        if (array_key_exists('payment_key_id', $input) && trim((string) $input['payment_key_id']) !== '') {
            $this->upsertCredential($provider, $environment, 'key_id', trim((string) $input['payment_key_id']), $actor, encrypted: false);
        }

        if (array_key_exists('payment_key_secret', $input) && trim((string) $input['payment_key_secret']) !== '') {
            $this->upsertCredential($provider, $environment, 'key_secret', trim((string) $input['payment_key_secret']), $actor, encrypted: true);
            $audit->log(AuditAction::PaymentSecretReplaced, null, null, [
                'scope' => 'payment_key_secret',
                'provider' => $provider->value,
                'environment' => $environment->value,
            ], $actor);
        }

        if (array_key_exists('payment_webhook_secret', $input) && trim((string) $input['payment_webhook_secret']) !== '') {
            $this->upsertCredential($provider, $environment, 'webhook_secret', trim((string) $input['payment_webhook_secret']), $actor, encrypted: true);
            $audit->log(AuditAction::PaymentSecretReplaced, null, null, [
                'scope' => 'payment_webhook_secret',
                'provider' => $provider->value,
                'environment' => $environment->value,
            ], $actor);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function safeSummary(): array
    {
        $provider = $this->activeProvider();
        $environment = $this->environment();

        return [
            'payment_enabled' => $this->isEnabled(),
            'payment_provider' => $provider->value,
            'payment_environment' => $environment->value,
            'payment_key_id_configured' => is_string($this->keyId($provider, $environment)) && $this->keyId($provider, $environment) !== '',
            'payment_key_secret_configured' => $this->keySecretConfigured($provider, $environment),
            'payment_webhook_secret_configured' => $this->webhookSecretConfigured($provider, $environment),
        ];
    }

    private function credentialKey(PaymentProvider $provider, PaymentEnvironment $environment, string $field): string
    {
        return "payment_{$provider->value}_{$environment->value}_{$field}";
    }

    private function storedCredential(PaymentProvider $provider, PaymentEnvironment $environment, string $field): ?string
    {
        $value = PlatformSetting::query()
            ->where('key', $this->credentialKey($provider, $environment, $field))
            ->value('value');

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function storedEncrypted(PaymentProvider $provider, PaymentEnvironment $environment, string $field): ?string
    {
        $value = PlatformSetting::query()
            ->where('key', $this->credentialKey($provider, $environment, $field))
            ->value('value');

        if (! is_array($value) || empty($value['encrypted'])) {
            return null;
        }

        try {
            return Crypt::decryptString($value['encrypted']);
        } catch (\Throwable) {
            return null;
        }
    }

    private function upsert(string $key, mixed $value, User $actor): void
    {
        PlatformSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'updated_by' => $actor->id],
        );
    }

    private function upsertCredential(
        PaymentProvider $provider,
        PaymentEnvironment $environment,
        string $field,
        string $value,
        User $actor,
        bool $encrypted,
    ): void {
        $key = $this->credentialKey($provider, $environment, $field);
        $stored = $encrypted ? ['encrypted' => Crypt::encryptString($value)] : $value;

        PlatformSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'updated_by' => $actor->id],
        );
    }
}
