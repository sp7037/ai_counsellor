<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\TenantNote;
use App\Models\User;

class TenantNotePolicy
{
    public function viewAny(User $user, Tenant $tenant): bool
    {
        return $user->isPlatformSuperAdmin() || $user->hasActiveMembership($tenant);
    }

    public function view(User $user, TenantNote $note): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return true;
        }

        return $user->hasActiveMembership($note->tenant);
    }

    public function create(User $user, Tenant $tenant): bool
    {
        return $user->hasActiveMembership($tenant);
    }

    public function update(User $user, TenantNote $note): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return true;
        }

        return $user->hasActiveMembership($note->tenant);
    }

    public function delete(User $user, TenantNote $note): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return true;
        }

        return $user->hasActiveMembership($note->tenant);
    }
}
