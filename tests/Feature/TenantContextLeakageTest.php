<?php

namespace Tests\Feature;

use App\Enums\Tenancy\TenantStatus;
use App\Models\Tenant;
use App\Models\TenantNote;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantContextLeakageTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_a_context_does_not_remain_active_for_tenant_b_request(): void
    {
        ['tenant' => $tenantA, 'user' => $user] = $this->createTenantWithMember();
        ['tenant' => $tenantB] = $this->createTenantWithMember();

        $this->actingAs($user);

        $this->get(route('tenant.dashboard', $tenantA))->assertOk();

        $context = app(TenantContext::class);
        $this->assertFalse($context->hasTenant());
        $this->assertFalse($context->isIsolationEnforced());

        $this->get(route('tenant.dashboard', $tenantB))->assertForbidden();
    }

    public function test_rejected_tenant_request_clears_context(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(
            tenantAttributes: [
                'status' => TenantStatus::Archived->value,
                'archived_at' => now(),
                'archive_reason' => 'Test',
            ],
        );

        $this->actingAs($user)->get(route('tenant.dashboard', $tenant))->assertForbidden();

        $context = app(TenantContext::class);
        $this->assertFalse($context->hasTenant());
        $this->assertFalse($context->isIsolationEnforced());
    }

    public function test_platform_route_does_not_inherit_previous_tenant_context(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        ['tenant' => $tenant, 'user' => $owner] = $this->createTenantWithMember();

        $this->actingAs($admin);
        $this->get(route('tenant.dashboard', $tenant))->assertOk();

        $context = app(TenantContext::class);
        $this->assertFalse($context->hasTenant());

        $this->get(route('platform.tenants.index'))->assertOk();
        $this->assertFalse($context->hasTenant());
    }

    public function test_context_does_not_leak_between_tests(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember();
        $this->withTenantContext($user, $tenant);

        $context = app(TenantContext::class);
        $this->assertTrue($context->hasTenant());

        $context->clear();
        $this->assertFalse($context->hasTenant());
    }

    public function test_unresolved_tenant_owned_queries_fail_closed_when_isolation_enforced(): void
    {
        TenantNote::query()->forceCreate([
            'tenant_id' => Tenant::factory()->active()->create()->id,
            'title' => 'Hidden',
            'created_by' => User::factory()->create()->id,
        ]);

        app(TenantContext::class)->enforceIsolation();

        $this->assertSame(0, TenantNote::query()->count());
    }

    public function test_explicitly_cleared_context_prevents_scoped_access(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember();

        TenantNote::query()->forceCreate([
            'tenant_id' => $tenant->id,
            'title' => 'Scoped note',
            'created_by' => $user->id,
        ]);

        $this->withTenantContext($user, $tenant);
        $this->assertSame(1, TenantNote::query()->count());

        app(TenantContext::class)->clear();
        app(TenantContext::class)->enforceIsolation();

        $this->assertSame(0, TenantNote::query()->count());
    }
}
