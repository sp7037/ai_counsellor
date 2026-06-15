<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantMembershipPolicy
{
    public function viewAny(User $user, Tenant $tenant): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return true;
        }

        $role = $user->tenantRoleFor($tenant);

        return $role?->canManageMembers() ?? false;
    }

    public function create(User $user, Tenant $tenant): bool
    {
        return $user->isPlatformSuperAdmin();
    }
}
