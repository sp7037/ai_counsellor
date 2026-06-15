<?php

namespace App\Services\Conversations;

use App\Enums\Conversations\ConversationMode;
use App\Enums\Tenancy\TenantRole;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\User;

class ConversationAccessService
{
    public function canSupervise(User $user, Tenant $tenant): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return false;
        }

        return $user->tenantRoleFor($tenant)?->canManageLeads() ?? false;
    }

    public function canView(User $user, Conversation $conversation): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return false;
        }

        if (! $user->hasActiveMembership($conversation->tenant)) {
            return false;
        }

        if ($this->canSupervise($user, $conversation->tenant)) {
            return true;
        }

        $role = $user->tenantRoleFor($conversation->tenant);

        if ($role !== TenantRole::Staff) {
            return false;
        }

        return $this->isAssignedCounsellor($user, $conversation);
    }

    public function canClaim(User $user, Conversation $conversation): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return false;
        }

        if ($conversation->mode !== ConversationMode::HandoffRequested) {
            return false;
        }

        if ($this->canSupervise($user, $conversation->tenant)) {
            return $user->tenantRoleFor($conversation->tenant) === TenantRole::Staff
                || $this->canSupervise($user, $conversation->tenant);
        }

        return $this->isEligibleCounsellor($user, $conversation);
    }

    public function canSendAsCounsellor(User $user, Conversation $conversation): bool
    {
        if ($conversation->mode !== ConversationMode::Human) {
            return false;
        }

        return $conversation->human_owner_id === $user->id;
    }

    public function isAssignedCounsellor(User $user, Conversation $conversation): bool
    {
        if ($conversation->human_owner_id === $user->id) {
            return true;
        }

        if ($conversation->target_counsellor_id === $user->id) {
            return true;
        }

        $conversation->loadMissing('lead');

        if ($conversation->lead?->assigned_to === $user->id) {
            return in_array($conversation->mode, [
                ConversationMode::HandoffRequested,
                ConversationMode::Human,
            ], true);
        }

        return false;
    }

    private function isEligibleCounsellor(User $user, Conversation $conversation): bool
    {
        if ($user->tenantRoleFor($conversation->tenant) !== TenantRole::Staff) {
            return false;
        }

        if ($conversation->target_counsellor_id !== null && $conversation->target_counsellor_id !== $user->id) {
            return false;
        }

        $conversation->loadMissing('lead');

        if ($conversation->lead !== null && $conversation->lead->assigned_to !== null && $conversation->lead->assigned_to !== $user->id) {
            return false;
        }

        return true;
    }
}
