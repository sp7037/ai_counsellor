<?php

namespace App\Services\Widget;

use App\Enums\Widget\TenantDomainStatus;
use App\Models\Tenant;
use App\Models\TenantDomain;

class WidgetDomainMatcher
{
    public function __construct(
        private readonly OriginValidator $originValidator,
    ) {}

    public function canonicalHost(?string $origin, ?string $referer = null): ?string
    {
        $host = $this->originValidator->extractOriginDomain($origin, $referer);

        if ($host === null) {
            return null;
        }

        return $this->originValidator->normalizeDomain($host);
    }

    public function isAllowedForTenant(Tenant $tenant, string $originDomain, ?string $fullOrigin = null): bool
    {
        if ($fullOrigin !== null && $this->originValidator->isAllowedLocalOrigin($fullOrigin)) {
            return true;
        }

        $canonical = $this->originValidator->normalizeDomain($originDomain);

        return $this->tenantHasVerifiedDomain($tenant, $canonical);
    }

    public function isVerifiedForAnyTenant(string $originDomain): bool
    {
        $canonical = $this->originValidator->normalizeDomain($originDomain);

        return TenantDomain::query()
            ->withoutGlobalScopes()
            ->where('status', TenantDomainStatus::Verified->value)
            ->where(function ($query) use ($canonical): void {
                $query->where('domain', $canonical)
                    ->orWhere('domain', 'www.'.$canonical);
            })
            ->exists();
    }

    private function tenantHasVerifiedDomain(Tenant $tenant, string $canonical): bool
    {
        return TenantDomain::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('status', TenantDomainStatus::Verified->value)
            ->where(function ($query) use ($canonical): void {
                $query->where('domain', $canonical)
                    ->orWhere('domain', 'www.'.$canonical);
            })
            ->exists();
    }
}
