<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantKnowledgePolicy
{
    public function viewAny(User $user, Tenant $tenant): bool
    {
        return $user->isPlatformSuperAdmin() || $user->hasActiveMembership($tenant);
    }

    public function manage(User $user, Tenant $tenant): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return true;
        }

        $role = $user->tenantRoleFor($tenant);

        return $role?->canManageKnowledge() ?? false;
    }
}
