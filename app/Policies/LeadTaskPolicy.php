<?php

namespace App\Policies;

use App\Models\LeadTask;
use App\Models\User;

class LeadTaskPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, LeadTask $task): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return false;
        }

        $role = $user->tenantRoleFor($task->tenant);

        if ($role?->canManageLeads()) {
            return $user->hasActiveMembership($task->tenant);
        }

        if ($role?->canWorkAssignedLeads()) {
            return $task->assigned_to_user_id === $user->id
                || $task->lead?->assigned_to === $user->id;
        }

        return false;
    }

    public function create(User $user, LeadTask $task): bool
    {
        return $this->canManageForLead($user, $task);
    }

    public function update(User $user, LeadTask $task): bool
    {
        return $this->canManageForLead($user, $task);
    }

    private function canManageForLead(User $user, LeadTask $task): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return false;
        }

        $lead = $task->lead;
        $role = $user->tenantRoleFor($task->tenant);

        if ($role?->canManageLeads()) {
            return $user->hasActiveMembership($task->tenant);
        }

        if ($role?->canWorkAssignedLeads() && $lead !== null) {
            return $lead->assigned_to === $user->id;
        }

        return false;
    }
}
