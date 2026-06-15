<?php

namespace App\Policies;

use App\Models\KnowledgeFee;
use App\Models\Tenant;
use App\Models\User;

class KnowledgeFeePolicy
{
    public function viewAny(User $user, Tenant $tenant): bool
    {
        return app(TenantKnowledgePolicy::class)->viewAny($user, $tenant);
    }

    public function create(User $user, Tenant $tenant): bool
    {
        return app(TenantKnowledgePolicy::class)->manage($user, $tenant);
    }

    public function update(User $user, KnowledgeFee $fee): bool
    {
        return app(TenantKnowledgePolicy::class)->manage($user, $fee->tenant);
    }

    public function publish(User $user, KnowledgeFee $fee): bool
    {
        return $this->update($user, $fee);
    }

    public function archive(User $user, KnowledgeFee $fee): bool
    {
        return $this->update($user, $fee);
    }
}
