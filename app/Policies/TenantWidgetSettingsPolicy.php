<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantWidgetSettingsPolicy
{
    public function view(User $user, Tenant $tenant): bool
    {
        return $user->isPlatformSuperAdmin() || $user->hasActiveMembership($tenant);
    }

    public function update(User $user, Tenant $tenant): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return true;
        }

        $role = $user->tenantRoleFor($tenant);

        return $role?->canManageWidget() ?? false;
    }
}
