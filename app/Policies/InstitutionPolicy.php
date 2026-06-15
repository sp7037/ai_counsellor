<?php

namespace App\Policies;

use App\Models\Institution;
use App\Models\Tenant;
use App\Models\User;

class InstitutionPolicy
{
    public function viewAny(User $user, Tenant $tenant): bool
    {
        return app(TenantConfigurationPolicy::class)->viewAny($user, $tenant);
    }

    public function create(User $user, Tenant $tenant): bool
    {
        return app(TenantConfigurationPolicy::class)->manage($user, $tenant);
    }

    public function update(User $user, Institution $institution): bool
    {
        return app(TenantConfigurationPolicy::class)->manage($user, $institution->tenant);
    }

    public function delete(User $user, Institution $institution): bool
    {
        return $this->update($user, $institution);
    }
}
