<?php

namespace App\Policies;

use App\Models\CourseInstitution;
use App\Models\Tenant;
use App\Models\User;

class CourseInstitutionPolicy
{
    public function viewAny(User $user, Tenant $tenant): bool
    {
        return app(TenantKnowledgePolicy::class)->viewAny($user, $tenant);
    }

    public function create(User $user, Tenant $tenant): bool
    {
        return app(TenantKnowledgePolicy::class)->manage($user, $tenant);
    }

    public function update(User $user, CourseInstitution $record): bool
    {
        return app(TenantKnowledgePolicy::class)->manage($user, $record->tenant);
    }

    public function publish(User $user, CourseInstitution $record): bool
    {
        return $this->update($user, $record);
    }

    public function archive(User $user, CourseInstitution $record): bool
    {
        return $this->update($user, $record);
    }
}
