<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Conversations\ConversationAccessService;

class ConversationPolicy
{
    public function viewAny(User $user, Tenant $tenant): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return false;
        }

        $access = app(ConversationAccessService::class);

        return $access->canSupervise($user, $tenant)
            || ($user->tenantRoleFor($tenant)?->usesCounsellorWorkspace() ?? false);
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return app(ConversationAccessService::class)->canView($user, $conversation);
    }

    public function supervise(User $user, Tenant $tenant): bool
    {
        return app(ConversationAccessService::class)->canSupervise($user, $tenant);
    }

    public function claim(User $user, Conversation $conversation): bool
    {
        return app(ConversationAccessService::class)->canClaim($user, $conversation);
    }

    public function sendAsCounsellor(User $user, Conversation $conversation): bool
    {
        return app(ConversationAccessService::class)->canSendAsCounsellor($user, $conversation);
    }

    public function assign(User $user, Conversation $conversation): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return false;
        }

        return app(ConversationAccessService::class)->canSupervise($user, $conversation->tenant);
    }

    public function convertToLead(User $user, Conversation $conversation): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return false;
        }

        if ($conversation->lead_id !== null) {
            return false;
        }

        return app(ConversationAccessService::class)->canSupervise($user, $conversation->tenant);
    }
}
