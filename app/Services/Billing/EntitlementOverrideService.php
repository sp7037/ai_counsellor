<?php

namespace App\Services\Billing;

use App\Enums\Audit\AuditAction;
use App\Enums\Billing\PlanFeature;
use App\Models\Tenant;
use App\Models\TenantEntitlementOverride;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class EntitlementOverrideService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly EntitlementResolver $entitlements,
    ) {}

    public function apply(
        Tenant $tenant,
        PlanFeature $feature,
        ?bool $enabled,
        ?int $limitValue,
        User $actor,
        string $reason,
        ?\DateTimeInterface $expiresAt = null,
    ): TenantEntitlementOverride {
        return DB::transaction(function () use ($tenant, $feature, $enabled, $limitValue, $actor, $reason, $expiresAt): TenantEntitlementOverride {
            $override = TenantEntitlementOverride::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'feature' => $feature->value],
                [
                    'enabled' => $enabled,
                    'limit_value' => $limitValue,
                    'reason' => $reason,
                    'expires_at' => $expiresAt,
                    'created_by' => $actor->id,
                ],
            );

            $this->audit->log(AuditAction::EntitlementOverrideApplied, $override, $tenant->id, [
                'feature' => $feature->value,
                'enabled' => $enabled,
                'limit_value' => $limitValue,
            ], $actor);

            $this->entitlements->clearCache();

            return $override;
        });
    }

    public function remove(Tenant $tenant, PlanFeature $feature, User $actor, ?string $reason = null): void
    {
        DB::transaction(function () use ($tenant, $feature, $actor, $reason): void {
            $override = TenantEntitlementOverride::query()
                ->where('tenant_id', $tenant->id)
                ->where('feature', $feature->value)
                ->first();

            if ($override === null) {
                return;
            }

            $override->delete();

            $this->audit->log(AuditAction::EntitlementOverrideRemoved, $tenant, $tenant->id, [
                'feature' => $feature->value,
                'reason' => $reason,
            ], $actor);

            $this->entitlements->clearCache();
        });
    }
}
