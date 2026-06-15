<?php

namespace App\Policies;

use App\Models\EligibilityRule;
use App\Models\Tenant;
use App\Models\User;

class EligibilityRulePolicy
{
    public function viewAny(User $user, Tenant $tenant): bool
    {
        return app(TenantKnowledgePolicy::class)->viewAny($user, $tenant);
    }

    public function create(User $user, Tenant $tenant): bool
    {
        return app(TenantKnowledgePolicy::class)->manage($user, $tenant);
    }

    public function update(User $user, EligibilityRule $rule): bool
    {
        return app(TenantKnowledgePolicy::class)->manage($user, $rule->tenant);
    }

    public function publish(User $user, EligibilityRule $rule): bool
    {
        return $this->update($user, $rule);
    }

    public function archive(User $user, EligibilityRule $rule): bool
    {
        return $this->update($user, $rule);
    }
}
