<?php

namespace App\Services\Auth;

use App\Models\Tenant;
use App\Models\User;

class PostLoginRedirect
{
    public function intendedUrl(User $user): string
    {
        if ($user->isPlatformSuperAdmin()) {
            return route('platform.tenants.index');
        }

        $memberships = $user->activeMemberships()
            ->with('tenant')
            ->get()
            ->filter(fn ($membership) => $membership->tenant->allowsTenantAccess());

        if ($memberships->isEmpty()) {
            return route('home');
        }

        if ($memberships->count() === 1) {
            /** @var Tenant $tenant */
            $tenant = $memberships->first()->tenant;

            return route('tenant.dashboard', $tenant);
        }

        return route('tenant.select');
    }
}
