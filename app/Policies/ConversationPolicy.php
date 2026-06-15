<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\User;

class ConversationPolicy
{
    public function viewAny(User $user, Tenant $tenant): bool
    {
        return $user->isPlatformSuperAdmin() || $user->hasActiveMembership($tenant);
    }

    public function view(User $user, Conversation $conversation): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return true;
        }

        return $user->hasActiveMembership($conversation->tenant);
    }
}
