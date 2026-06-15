<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\TenantMessagingIntegration;
use App\Models\User;

class TenantMessagingIntegrationPolicy
{
    public function viewAny(User $user, Tenant $tenant): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return true;
        }

        $membership = $user->membershipFor($tenant);

        return $membership?->status?->allowsAccess() === true
            && $membership->role?->canManageIntegrations() === true;
    }

    public function view(User $user, TenantMessagingIntegration $integration): bool
    {
        if ($user->isPlatformSuperAdmin()) {
            return true;
        }

        $membership = $user->membershipFor($integration->tenant);

        return $membership?->status?->allowsAccess() === true
            && $membership->role?->canManageIntegrations() === true;
    }

    public function update(User $user, TenantMessagingIntegration $integration): bool
    {
        return $this->view($user, $integration);
    }

    public function configure(User $user, TenantMessagingIntegration $integration): bool
    {
        return $this->update($user, $integration);
    }

    public function enable(User $user, TenantMessagingIntegration $integration): bool
    {
        return $this->update($user, $integration);
    }

    public function disable(User $user, TenantMessagingIntegration $integration): bool
    {
        return $this->update($user, $integration);
    }
}
