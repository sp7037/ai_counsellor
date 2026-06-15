<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ConfigurationIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_a_admin_cannot_delete_tenant_b_service(): void
    {
        ['tenant' => $tenantA, 'user' => $userA] = $this->createTenantWithMember();
        ['tenant' => $tenantB, 'user' => $userB] = $this->createTenantWithMember();

        app(TenantContext::class)->clear();
        app(TenantContext::class)->resolveForUser($userB, $tenantB);
        app(TenantContext::class)->enforceIsolation();

        $serviceB = Service::query()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'Tenant B service',
            'slug' => 'tenant-b-service',
            'status' => 'active',
            'created_by' => $userB->id,
        ]);

        app(TenantContext::class)->clear();
        $this->actingAs($userA);
        $this->withTenantContext($userA, $tenantA);

        Volt::test('tenant.configuration.services', ['tenant' => $tenantA])
            ->call('deleteItem', $serviceB->uuid)
            ->assertStatus(404);

        $this->assertDatabaseHas('services', ['id' => $serviceB->id]);
    }

    public function test_tenant_a_cannot_view_tenant_b_services_in_list(): void
    {
        ['tenant' => $tenantA, 'user' => $userA] = $this->createTenantWithMember();
        ['tenant' => $tenantB, 'user' => $userB] = $this->createTenantWithMember();

        app(TenantContext::class)->clear();
        app(TenantContext::class)->resolveForUser($userB, $tenantB);
        app(TenantContext::class)->enforceIsolation();
        Service::query()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'Hidden',
            'slug' => 'hidden',
            'status' => 'active',
            'created_by' => $userB->id,
        ]);

        $this->actingAs($userA);
        $this->withTenantContext($userA, $tenantA);

        $this->assertFalse(Service::query()->where('name', 'Hidden')->exists());
    }
}
