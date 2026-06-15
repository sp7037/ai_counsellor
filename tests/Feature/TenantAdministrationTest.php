<?php

namespace Tests\Feature;

use App\Enums\Audit\AuditAction;
use App\Enums\Tenancy\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TenantAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_super_admin_can_create_tenant(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();

        $this->actingAs($admin);

        Volt::test('platform.tenants.create')
            ->set('name', 'Acme Counselling')
            ->set('slug', 'acme-counselling')
            ->set('create_owner', true)
            ->set('owner_name', 'Owner User')
            ->set('owner_email', 'owner@example.test')
            ->set('owner_password', 'secure-password-1')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('tenants', [
            'name' => 'Acme Counselling',
            'slug' => 'acme-counselling',
            'status' => TenantStatus::Pending->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::TenantCreated->value,
        ]);
    }

    public function test_tenant_activation_is_recorded(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        $tenant = Tenant::factory()->create();

        $service = app(TenantLifecycleService::class);
        $service->activate($tenant, $admin);

        $tenant->refresh();

        $this->assertSame(TenantStatus::Active, $tenant->status);
        $this->assertNotNull($tenant->activated_at);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'action' => AuditAction::TenantActivated->value,
        ]);
    }

    public function test_tenant_suspension_preserves_data_and_stores_reason(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        $tenant = Tenant::factory()->active()->create();
        Tenant::factory()->active()->create();

        $service = app(TenantLifecycleService::class);
        $service->suspend($tenant, 'Policy violation', $admin);

        $tenant->refresh();

        $this->assertSame(TenantStatus::Suspended, $tenant->status);
        $this->assertSame('Policy violation', $tenant->suspension_reason);
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::TenantSuspended->value,
        ]);
    }

    public function test_unauthorized_status_changes_are_rejected(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember();

        $this->actingAs($user)
            ->get(route('platform.tenants.show', $tenant))
            ->assertForbidden();
    }
}
