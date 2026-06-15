<?php

namespace App\Policies;

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
        return $user->isPlatformSuperAdmin();
    }

    public function suspend(User $user, Tenant $tenant): bool
    {
        return $user->isPlatformSuperAdmin();
    }

    public function reactivate(User $user, Tenant $tenant): bool
    {
        return $user->isPlatformSuperAdmin();
    }
}
