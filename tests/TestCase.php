<?php

namespace Tests;

use App\Enums\Tenancy\MembershipStatus;
use App\Enums\Tenancy\TenantRole;
use App\Enums\Tenancy\TenantStatus;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        app(TenantContext::class)->clear();

        parent::tearDown();
    }

    protected function createTenantWithMember(
        array $tenantAttributes = [],
        ?User $user = null,
        TenantRole $role = TenantRole::Staff,
        MembershipStatus $membershipStatus = MembershipStatus::Active,
    ): array {
        $tenant = Tenant::factory()->create(array_merge([
            'status' => TenantStatus::Active->value,
            'activated_at' => now(),
        ], $tenantAttributes));

        $user ??= User::factory()->create();

        $membership = TenantMembership::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => $role->value,
            'status' => $membershipStatus->value,
            'is_owner' => $role === TenantRole::Owner,
        ]);

        return compact('tenant', 'user', 'membership');
    }

    protected function withTenantContext(User $user, Tenant $tenant): TenantContext
    {
        $context = app(TenantContext::class);
        $context->clear();
        $context->resolveForUser($user, $tenant);
        $context->enforceIsolation();

        return $context;
    }
}
