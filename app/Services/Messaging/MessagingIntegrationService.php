<?php

namespace App\Services\Messaging;

use App\Enums\Audit\AuditAction;
use App\Enums\Billing\PlanFeature;
use App\Enums\Messaging\MessagingEventType;
use App\Enums\Messaging\MessagingIntegrationStatus;
use App\Enums\Messaging\MessagingProvider;
use App\Models\Tenant;
use App\Models\TenantMessagingIntegration;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\EntitlementResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MessagingIntegrationService
{
    public function __construct(
        private readonly MessagingCredentialService $credentials,
        private readonly MessagingEventRecorder $events,
        private readonly EntitlementResolver $entitlements,
        private readonly AuditLogger $audit,
    ) {}

    public function forTenant(Tenant $tenant): TenantMessagingIntegration
    {
        return TenantMessagingIntegration::query()->firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'provider' => app(MessagingProviderRegistry::class)->defaultProvider()->value,
                'environment' => (string) config('messaging.environment', 'test'),
                'status' => MessagingIntegrationStatus::Disabled->value,
                'verify_token' => Str::random(32),
                'is_enabled' => false,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function configure(Tenant $tenant, array $input, User $actor): TenantMessagingIntegration
    {
        $this->entitlements->assertAllowed($tenant, PlanFeature::WhatsAppIntegration);

        $integration = $this->forTenant($tenant);

        return DB::transaction(function () use ($integration, $input, $actor, $tenant): TenantMessagingIntegration {
            $updates = ['configured_by' => $actor->id];

            if (isset($input['provider'])) {
                $updates['provider'] = MessagingProvider::from((string) $input['provider'])->value;
            }

            if (isset($input['environment'])) {
                $updates['environment'] = (string) $input['environment'];
            }

            if (isset($input['phone_number_id']) && trim((string) $input['phone_number_id']) !== '') {
                $updates['phone_number_id'] = trim((string) $input['phone_number_id']);
            }

            if (isset($input['waba_id']) && trim((string) $input['waba_id']) !== '') {
                $updates['waba_id'] = trim((string) $input['waba_id']);
            }

            if (isset($input['display_phone_number'])) {
                $updates['display_phone_number'] = trim((string) $input['display_phone_number']) ?: null;
            }

            if (isset($input['business_display_name'])) {
                $updates['business_display_name'] = trim((string) $input['business_display_name']) ?: null;
            }

            if (isset($input['verify_token']) && trim((string) $input['verify_token']) !== '') {
                $updates['verify_token'] = trim((string) $input['verify_token']);
            }

            $integration->update($updates);

            if (array_key_exists('access_token', $input) && trim((string) $input['access_token']) !== '') {
                $this->credentials->storeAccessToken($integration, (string) $input['access_token']);
                $this->audit->log(AuditAction::MessagingCredentialReplaced, $tenant, null, [
                    'scope' => 'access_token',
                ], $actor);
                $this->events->record(
                    MessagingEventType::CredentialReplaced,
                    $integration,
                    metadata: ['scope' => 'access_token'],
                );
            }

            if (array_key_exists('app_secret', $input) && trim((string) $input['app_secret']) !== '') {
                $this->credentials->storeAppSecret($integration, (string) $input['app_secret']);
                $this->audit->log(AuditAction::MessagingCredentialReplaced, $tenant, null, [
                    'scope' => 'app_secret',
                ], $actor);
                $this->events->record(
                    MessagingEventType::CredentialReplaced,
                    $integration,
                    metadata: ['scope' => 'app_secret'],
                );
            }

            if ($this->credentials->accessTokenConfigured($integration->fresh())
                && $this->credentials->appSecretConfigured($integration->fresh())
                && is_string($integration->phone_number_id)
                && $integration->phone_number_id !== '') {
                $integration->update(['status' => MessagingIntegrationStatus::Connected->value]);
            } else {
                $integration->update(['status' => MessagingIntegrationStatus::Pending->value]);
            }

            $this->audit->log(AuditAction::MessagingIntegrationConfigured, $tenant, null, [
                'integration_uuid' => $integration->uuid,
            ], $actor);

            return $integration->fresh();
        });
    }

    public function enable(Tenant $tenant, User $actor): TenantMessagingIntegration
    {
        $this->entitlements->assertAllowed($tenant, PlanFeature::WhatsAppIntegration);

        $integration = $this->forTenant($tenant);

        if (! $this->credentials->accessTokenConfigured($integration)
            || ! is_string($integration->phone_number_id)
            || $integration->phone_number_id === '') {
            throw new \RuntimeException('WhatsApp integration is not fully configured.');
        }

        $integration->update([
            'is_enabled' => true,
            'status' => MessagingIntegrationStatus::Connected->value,
            'configured_by' => $actor->id,
        ]);

        $this->events->record(MessagingEventType::IntegrationEnabled, $integration);
        $this->audit->log(AuditAction::MessagingIntegrationEnabled, $tenant, null, [
            'integration_uuid' => $integration->uuid,
        ], $actor);

        return $integration->fresh();
    }

    public function disable(Tenant $tenant, User $actor): TenantMessagingIntegration
    {
        $integration = $this->forTenant($tenant);

        $integration->update([
            'is_enabled' => false,
            'status' => MessagingIntegrationStatus::Disabled->value,
        ]);

        $this->events->record(MessagingEventType::IntegrationDisabled, $integration);
        $this->audit->log(AuditAction::MessagingIntegrationDisabled, $tenant, null, [
            'integration_uuid' => $integration->uuid,
        ], $actor);

        return $integration->fresh();
    }

    public function disconnect(Tenant $tenant, User $actor): TenantMessagingIntegration
    {
        return $this->disable($tenant, $actor);
    }

    /**
     * @return array<string, mixed>
     */
    public function testConnection(Tenant $tenant, User $actor): array
    {
        $this->entitlements->assertAllowed($tenant, PlanFeature::WhatsAppIntegration);

        $integration = $this->forTenant($tenant);

        if (! $this->credentials->accessTokenConfigured($integration)
            || ! is_string($integration->phone_number_id)
            || $integration->phone_number_id === '') {
            throw new \RuntimeException('WhatsApp integration is not fully configured.');
        }

        $this->audit->log(AuditAction::MessagingIntegrationConfigured, $tenant, null, [
            'integration_uuid' => $integration->uuid,
            'action' => 'test_connection',
        ], $actor);

        return [
            'status' => 'ok',
            'summary' => $this->credentials->safeSummary($integration->fresh()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function safeSummary(Tenant $tenant): array
    {
        $integration = $this->forTenant($tenant);

        return $this->credentials->safeSummary($integration);
    }
}
