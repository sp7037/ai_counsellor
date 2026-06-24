<?php

namespace App\Services\Auth;

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Support\Collection;

class PostLoginRedirect
{
    /**
     * @return Collection<int, TenantMembership>
     */
    public function accessibleMemberships(User $user): Collection
    {
        return $user->activeMemberships()
            ->with('tenant')
            ->get()
            ->filter(fn (TenantMembership $membership) => $membership->tenant->allowsWorkspaceEntry())
            ->values();
    }

    public function intendedUrl(User $user): string
    {
        if ($user->isPlatformSuperAdmin()) {
            return route('platform.overview');
        }

        $memberships = $this->accessibleMemberships($user);

        if ($memberships->isEmpty()) {
            return route('tenant.select');
        }

        if ($memberships->count() === 1) {
            /** @var Tenant $tenant */
            $tenant = $memberships->first()->tenant;
            $role = $user->tenantRoleFor($tenant);

            if ($role?->usesCounsellorWorkspace()) {
                return route('workspace.dashboard', $tenant);
            }

            return route('tenant.dashboard', $tenant);
        }

        return route('tenant.select');
    }
}
