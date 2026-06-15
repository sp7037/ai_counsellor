<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;

class SubscriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformSuperAdmin();
    }

    public function view(User $user, Subscription $subscription): bool
    {
        return $user->isPlatformSuperAdmin();
    }

    public function manage(User $user): bool
    {
        return $user->isPlatformSuperAdmin();
    }
}
