<?php

namespace Tests\Feature;

use App\Enums\Tenancy\TenantRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ordinary_users_cannot_access_platform_routes(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Admin);

        $this->actingAs($user)
            ->get(route('platform.tenants.index'))
            ->assertForbidden();
    }

    public function test_tenant_admins_cannot_access_platform_routes(): void
    {
        ['user' => $user] = $this->createTenantWithMember(role: TenantRole::Admin);

        $this->actingAs($user)
            ->get(route('platform.tenants.create'))
            ->assertForbidden();
    }

    public function test_platform_super_admin_can_access_tenant_management_routes(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        $tenant = Tenant::factory()->create();

        $this->actingAs($admin)
            ->get(route('platform.tenants.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('platform.tenants.show', $tenant))
            ->assertOk();
    }
}
