<?php

namespace App\Policies;

use App\Models\Plan;
use App\Models\User;

class PlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformSuperAdmin();
    }

    public function view(User $user, Plan $plan): bool
    {
        return $user->isPlatformSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isPlatformSuperAdmin();
    }

    public function update(User $user, Plan $plan): bool
    {
        return $user->isPlatformSuperAdmin();
    }
}
