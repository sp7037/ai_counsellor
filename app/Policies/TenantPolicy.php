<?php

namespace App\Policies;

use App\Enums\Tenancy\TenantStatus;
use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformSuperAdmin();
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $user->isPlatformSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isPlatformSuperAdmin();
    }

    public function activate(User $user, Tenant $tenant): bool
    {
        return $user->isPlatformSuperAdmin()
            && $tenant->status === TenantStatus::Pending;
    }

    public function suspend(User $user, Tenant $tenant): bool
    {
        return $user->isPlatformSuperAdmin()
            && in_array($tenant->status, [TenantStatus::Active, TenantStatus::Pending], true);
    }

    public function reactivate(User $user, Tenant $tenant): bool
    {
        return $user->isPlatformSuperAdmin()
            && $tenant->status === TenantStatus::Suspended;
    }

    public function archive(User $user, Tenant $tenant): bool
    {
        return $user->isPlatformSuperAdmin()
            && $tenant->status->canBeArchived();
    }

    public function restore(User $user, Tenant $tenant): bool
    {
        return $user->isPlatformSuperAdmin()
            && in_array($tenant->status, [TenantStatus::Archived, TenantStatus::Deleted], true);
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->isPlatformSuperAdmin()
            && $tenant->status->canBeDeleted();
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $user->isPlatformSuperAdmin();
    }
}
