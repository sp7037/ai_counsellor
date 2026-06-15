<?php

namespace App\Policies;

use App\Enums\Tenancy\TenantRole;
use App\Models\Tenant;
use App\Models\TenantMembership;
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
        if ($user->isPlatformSuperAdmin()) {
            return true;
        }

        return $user->tenantRoleFor($tenant)?->canManageMembers() ?? false;
    }

    public function updateRole(User $user, TenantMembership $membership): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return true;
        }

        if ($user->id === $membership->user_id) {
            return false;
        }

        $actorRole = $user->tenantRoleFor($membership->tenant);

        return $actorRole?->canManageMembers() ?? false;
    }

    public function updateStatus(User $user, TenantMembership $membership): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return true;
        }

        if ($user->id === $membership->user_id) {
            return false;
        }

        $actorRole = $user->tenantRoleFor($membership->tenant);

        return $actorRole?->canManageMembers() ?? false;
    }

    public function delete(User $user, TenantMembership $membership): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return true;
        }

        if ($user->id === $membership->user_id) {
            return false;
        }

        $actorRole = $user->tenantRoleFor($membership->tenant);

        if ($actorRole === TenantRole::Owner) {
            return true;
        }

        return $actorRole === TenantRole::Admin
            && $membership->role === TenantRole::Staff;
    }
}
