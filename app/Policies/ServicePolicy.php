<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;

class ServicePolicy
{
    public function viewAny(User $user, Tenant $tenant): bool
    {
        return app(TenantConfigurationPolicy::class)->viewAny($user, $tenant);
    }

    public function create(User $user, Tenant $tenant): bool
    {
        return app(TenantConfigurationPolicy::class)->manage($user, $tenant);
    }

    public function update(User $user, Service $service): bool
    {
        return app(TenantConfigurationPolicy::class)->manage($user, $service->tenant);
    }

    public function delete(User $user, Service $service): bool
    {
        return $this->update($user, $service);
    }
}
