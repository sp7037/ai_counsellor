<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\Tenant;
use App\Models\User;

class CoursePolicy
{
    public function viewAny(User $user, Tenant $tenant): bool
    {
        return app(TenantConfigurationPolicy::class)->viewAny($user, $tenant);
    }

    public function create(User $user, Tenant $tenant): bool
    {
        return app(TenantConfigurationPolicy::class)->manage($user, $tenant);
    }

    public function update(User $user, Course $course): bool
    {
        return app(TenantConfigurationPolicy::class)->manage($user, $course->tenant);
    }

    public function delete(User $user, Course $course): bool
    {
        return $this->update($user, $course);
    }
}
