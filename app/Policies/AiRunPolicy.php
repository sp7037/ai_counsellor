<?php

namespace App\Policies;

use App\Models\AiRun;
use App\Models\User;

class AiRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformSuperAdmin();
    }

    public function view(User $user, AiRun $run): bool
    {
        return $user->isPlatformSuperAdmin();
    }
}
