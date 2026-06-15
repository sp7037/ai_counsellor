<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;
use App\Models\WidgetKey;

class WidgetKeyPolicy
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

    public function rotate(User $user, WidgetKey $key): bool
    {
        return $this->manage($user, $key);
    }

    public function revoke(User $user, WidgetKey $key): bool
    {
        return $this->manage($user, $key);
    }

    private function manage(User $user, WidgetKey $key): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return true;
        }

        $role = $user->tenantRoleFor($key->tenant);

        return $role?->canManageWidget() ?? false;
    }
}
