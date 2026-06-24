<?php

namespace App\Services\Tenancy;

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;

class TenantContext
{
    private ?Tenant $tenant = null;

    private ?TenantMembership $membership = null;

    private bool $platformBypass = false;

    private bool $isolationEnforced = false;

    public function set(Tenant $tenant, TenantMembership $membership): void
    {
        $this->tenant = $tenant;
        $this->membership = $membership;
        $this->platformBypass = false;
    }

    public function setPlatformBypass(Tenant $tenant): void
    {
        $this->tenant = $tenant;
        $this->membership = null;
        $this->platformBypass = true;
    }

    public function setFromWidgetGateway(Tenant $tenant): void
    {
        $this->tenant = $tenant;
        $this->membership = null;
        $this->platformBypass = false;
    }

    public function enforceIsolation(): void
    {
        $this->isolationEnforced = true;
    }

    public function clear(): void
    {
        $this->tenant = null;
        $this->membership = null;
        $this->platformBypass = false;
        $this->isolationEnforced = false;
    }

    public function hasTenant(): bool
    {
        return $this->tenant !== null;
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function membership(): ?TenantMembership
    {
        return $this->membership;
    }

    public function tenantId(): ?int
    {
        return $this->tenant?->id;
    }

    public function isPlatformBypass(): bool
    {
        return $this->platformBypass;
    }

    public function isIsolationEnforced(): bool
    {
        return $this->isolationEnforced;
    }

    public function resolveForUser(User $user, Tenant $tenant): void
    {
        if ($user->isPlatformSuperAdmin()) {
            $this->setPlatformBypass($tenant);

            return;
        }

        $membership = $user->membershipFor($tenant);

        if ($membership === null || ! $membership->allowsAccess()) {
            abort(403);
        }

        if (! $tenant->allowsWorkspaceEntry()) {
            abort(403, 'This organisation is not available.');
        }

        $this->set($tenant, $membership);
    }
}
