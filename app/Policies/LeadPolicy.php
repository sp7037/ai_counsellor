<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;

class LeadPolicy
{
    public function viewAny(User $user, Tenant $tenant): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return false;
        }

        $role = $user->tenantRoleFor($tenant);

        return $role?->canManageLeads() || $role?->canWorkAssignedLeads();
    }

    public function view(User $user, Lead $lead): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return false;
        }

        $role = $user->tenantRoleFor($lead->tenant);

        if ($role?->canManageLeads()) {
            return $user->hasActiveMembership($lead->tenant);
        }

        if ($role?->canWorkAssignedLeads()) {
            return $lead->assigned_to === $user->id;
        }

        return false;
    }

    public function create(User $user, Tenant $tenant): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return false;
        }

        return $user->tenantRoleFor($tenant)?->canManageLeads() ?? false;
    }

    public function update(User $user, Lead $lead): bool
    {
        return $this->view($user, $lead);
    }

    public function assign(User $user, Lead $lead): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return false;
        }

        return $user->tenantRoleFor($lead->tenant)?->canManageLeads() ?? false;
    }

    public function manageWorkflow(User $user, Lead $lead): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return false;
        }

        if ($lead->trashed()) {
            return false;
        }

        $role = $user->tenantRoleFor($lead->tenant);

        if ($role?->canManageLeads()) {
            return true;
        }

        return $role?->canWorkAssignedLeads() && $lead->assigned_to === $user->id;
    }

    public function delete(User $user, Lead $lead): bool
    {
        if ($user->isPlatformSuperAdmin() || $lead->trashed()) {
            return false;
        }

        return $user->tenantRoleFor($lead->tenant)?->canManageLeads() ?? false;
    }

    public function restore(User $user, Lead $lead): bool
    {
        if ($user->isPlatformSuperAdmin() || ! $lead->trashed()) {
            return false;
        }

        return $user->tenantRoleFor($lead->tenant)?->canManageLeads() ?? false;
    }
}
