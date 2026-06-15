<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;

class TenantDomainPolicy
{
    public function viewAny(User $user, Tenant $tenant): bool
    {
        return $user->isPlatformSuperAdmin() || $user->hasActiveMembership($tenant);
    }

    public function create(User $user, Tenant $tenant): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return true;
        }

        $role = $user->tenantRoleFor($tenant);

        return $role?->canManageWidget() ?? false;
    }

    public function verify(User $user, TenantDomain $domain): bool
    {
        return $this->manage($user, $domain);
    }

    public function delete(User $user, TenantDomain $domain): bool
    {
        return $this->manage($user, $domain);
    }

    private function manage(User $user, TenantDomain $domain): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return true;
        }

        $role = $user->tenantRoleFor($domain->tenant);

        return $role?->canManageWidget() ?? false;
    }
}
