<?php

namespace App\Policies;

use App\Models\KnowledgeItem;
use App\Models\Tenant;
use App\Models\User;

class KnowledgeItemPolicy
{
    public function viewAny(User $user, Tenant $tenant): bool
    {
        return app(TenantKnowledgePolicy::class)->viewAny($user, $tenant);
    }

    public function view(User $user, KnowledgeItem $item): bool
    {
        return $this->viewAny($user, $item->tenant);
    }

    public function create(User $user, Tenant $tenant): bool
    {
        return app(TenantKnowledgePolicy::class)->manage($user, $tenant);
    }

    public function update(User $user, KnowledgeItem $item): bool
    {
        return app(TenantKnowledgePolicy::class)->manage($user, $item->tenant);
    }

    public function publish(User $user, KnowledgeItem $item): bool
    {
        return $this->update($user, $item);
    }

    public function archive(User $user, KnowledgeItem $item): bool
    {
        return $this->update($user, $item);
    }

    public function delete(User $user, KnowledgeItem $item): bool
    {
        return $this->update($user, $item);
    }
}
