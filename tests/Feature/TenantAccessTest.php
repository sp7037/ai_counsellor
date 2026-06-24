<?php

namespace Tests\Feature;

use App\Enums\Tenancy\MembershipStatus;
use App\Enums\Tenancy\TenantRole;
use App\Enums\Tenancy\TenantStatus;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_tenant_member_can_access_own_tenant_dashboard(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember();

        $this->actingAs($user)
            ->get(route('tenant.dashboard', $tenant))
            ->assertOk();
    }

    public function test_user_without_membership_cannot_access_tenant(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('tenant.dashboard', $tenant))
            ->assertForbidden();
    }

    public function test_inactive_membership_is_rejected(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(
            membershipStatus: MembershipStatus::Inactive,
        );

        $this->actingAs($user)
            ->get(route('tenant.dashboard', $tenant))
            ->assertForbidden();
    }

    public function test_suspended_tenant_member_can_access_dashboard(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(
            tenantAttributes: [
                'status' => TenantStatus::Suspended->value,
                'suspended_at' => now(),
                'suspension_reason' => 'Billing hold',
            ],
        );

        $this->actingAs($user)
            ->get(route('tenant.dashboard', $tenant))
            ->assertOk();
    }

    public function test_cancelled_tenant_is_rejected(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(
            tenantAttributes: ['status' => TenantStatus::Cancelled->value],
        );

        $this->actingAs($user)
            ->get(route('tenant.dashboard', $tenant))
            ->assertForbidden();
    }

    public function test_pending_tenant_member_can_access_dashboard(): void
    {
        $tenant = Tenant::factory()->create(['status' => TenantStatus::Pending->value]);
        $user = User::factory()->create();

        TenantMembership::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => TenantRole::Owner->value,
            'status' => MembershipStatus::Active->value,
            'is_owner' => true,
        ]);

        $this->actingAs($user)
            ->get(route('tenant.dashboard', $tenant))
            ->assertOk();
    }
}
