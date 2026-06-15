<?php

namespace App\Policies;

use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;

class LocationPolicy
{
    public function viewAny(User $user, Tenant $tenant): bool
    {
        return app(TenantConfigurationPolicy::class)->viewAny($user, $tenant);
    }

    public function create(User $user, Tenant $tenant): bool
    {
        return app(TenantConfigurationPolicy::class)->manage($user, $tenant);
    }

    public function update(User $user, Location $location): bool
    {
        return app(TenantConfigurationPolicy::class)->manage($user, $location->tenant);
    }

    public function delete(User $user, Location $location): bool
    {
        return $this->update($user, $location);
    }
}
