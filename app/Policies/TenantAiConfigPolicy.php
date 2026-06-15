<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\TenantAiConfig;
use App\Models\User;

class TenantAiConfigPolicy
{
    public function viewAny(User $user, Tenant $tenant): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return true;
        }

        $membership = $user->membershipFor($tenant);

        return $membership?->status?->allowsAccess() === true
            && $membership->role?->canManageMembers() === true;
    }

    public function update(User $user, TenantAiConfig $config): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return true;
        }

        $membership = $user->membershipFor($config->tenant);

        return $membership?->status?->allowsAccess() === true
            && $membership->role?->canManageMembers() === true;
    }
}
