<?php

namespace Tests\Feature;

use App\Enums\Audit\AuditAction;
use App\Enums\Billing\PlanChangeRequestStatus;
use App\Enums\PlatformRole;
use App\Enums\Tenancy\TenantRole;
use App\Enums\Tenancy\TenantStatus;
use App\Enums\UserStatus;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantPlanChangeRequest;
use App\Models\User;
use App\Services\Billing\PlanChangeRequestService;
use App\Services\Platform\PlatformUserLookupService;
use App\Services\Tenancy\TenantLifecycleService;
use App\Services\Tenancy\TenantProfileService;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TenantSaasLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleted_tenant_releases_slug_and_email(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        $tenant = Tenant::factory()->suspended()->create([
            'slug' => 'acme-clinic',
            'email' => 'contact@acme.test',
        ]);

        app(TenantLifecycleService::class)->archive($tenant, 'Archive', $admin);
        app(TenantLifecycleService::class)->softDelete($tenant->fresh(), 'Delete', $admin);

        $tenant->refresh();
        $this->assertSame('acme-clinic', $tenant->original_slug);
        $this->assertSame('contact@acme.test', $tenant->original_email);
        $this->assertStringEndsWith('-deleted-'.$tenant->id, $tenant->slug);
        $this->assertStringEndsWith('@deleted.local', (string) $tenant->email);
    }

    public function test_new_tenant_can_use_slug_after_previous_tenant_deleted(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        $tenant = Tenant::factory()->suspended()->create(['slug' => 'reuse-slug']);
        app(TenantLifecycleService::class)->archive($tenant, 'Archive', $admin);
        app(TenantLifecycleService::class)->softDelete($tenant->fresh(), 'Delete', $admin);

        $this->actingAs($admin);

        Volt::test('platform.tenants.create')
            ->set('name', 'Reuse Org')
            ->set('slug', 'reuse-slug')
            ->set('create_owner', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tenants', [
            'slug' => 'reuse-slug',
            'status' => TenantStatus::Pending->value,
        ]);
    }

    public function test_restore_detects_slug_conflict_when_slug_reused(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        $deleted = Tenant::factory()->suspended()->create(['slug' => 'conflict-slug']);
        app(TenantLifecycleService::class)->archive($deleted, 'Archive', $admin);
        app(TenantLifecycleService::class)->softDelete($deleted->fresh(), 'Delete', $admin);

        Tenant::factory()->active()->create(['slug' => 'conflict-slug']);

        app(TenantLifecycleService::class)->restoreFromDelete($deleted->fresh(), $admin);

        $deleted->refresh();
        $this->assertSame(TenantStatus::Suspended, $deleted->status);
        $this->assertTrue($deleted->identifier_restore_conflict);
        $this->assertStringContainsString('-deleted-', $deleted->slug);
    }

    public function test_deleted_tenant_owner_email_is_released_when_only_tenant(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        ['tenant' => $tenant, 'user' => $owner] = $this->createTenantWithMember(role: TenantRole::Owner);

        app(TenantLifecycleService::class)->suspend($tenant, 'Hold', $admin);
        app(TenantLifecycleService::class)->archive($tenant->fresh(), 'Archive', $admin);
        app(TenantLifecycleService::class)->softDelete($tenant->fresh(), 'Delete', $admin);

        $owner->refresh();
        $this->assertSame(UserStatus::Disabled, $owner->status);
        $this->assertStringEndsWith('@deleted.local', $owner->email);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::UserEmailReleased->value]);
    }

    public function test_owner_email_not_released_when_user_belongs_to_another_tenant(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        ['tenant' => $tenantA, 'user' => $owner] = $this->createTenantWithMember(role: TenantRole::Owner);
        ['tenant' => $tenantB] = $this->createTenantWithMember([], $owner, TenantRole::Admin);

        app(TenantLifecycleService::class)->suspend($tenantA, 'Hold', $admin);
        app(TenantLifecycleService::class)->archive($tenantA->fresh(), 'Archive', $admin);
        app(TenantLifecycleService::class)->softDelete($tenantA->fresh(), 'Delete', $admin);

        $originalEmail = $owner->fresh()->email;
        $this->assertStringNotContainsString('@deleted.local', $originalEmail);
    }

    public function test_new_tenant_can_be_created_with_released_owner_email(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        ['tenant' => $tenant, 'user' => $owner] = $this->createTenantWithMember(role: TenantRole::Owner);
        $ownerEmail = $owner->email;

        app(TenantLifecycleService::class)->suspend($tenant, 'Hold', $admin);
        app(TenantLifecycleService::class)->archive($tenant->fresh(), 'Archive', $admin);
        app(TenantLifecycleService::class)->softDelete($tenant->fresh(), 'Delete', $admin);

        $this->actingAs($admin);

        Volt::test('platform.tenants.create')
            ->set('name', 'Fresh Org')
            ->set('slug', 'fresh-org')
            ->set('create_owner', true)
            ->set('owner_name', 'Fresh Owner')
            ->set('owner_email', $ownerEmail)
            ->set('owner_password', 'secure-password-12')
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_super_admin_can_edit_tenant_profile(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        $tenant = Tenant::factory()->active()->create(['name' => 'Old Name']);

        $this->actingAs($admin);

        Volt::test('platform.tenants.show', ['tenant' => $tenant])
            ->call('openEdit')
            ->set('edit_name', 'New Name')
            ->set('edit_slug', $tenant->slug)
            ->call('saveProfile')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'name' => 'New Name']);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::TenantUpdated->value]);
    }

    public function test_tenant_admin_can_submit_plan_change_request(): void
    {
        $this->seed(PlansSeeder::class);
        ['tenant' => $tenant, 'user' => $admin] = $this->createTenantWithMember(role: TenantRole::Admin);
        $plan = Plan::query()->where('code', 'starter')->firstOrFail();

        $this->actingAs($admin);

        Volt::test('tenant.subscription', ['tenant' => $tenant])
            ->set('request_open', true)
            ->set('requested_plan_id', $plan->id)
            ->set('request_reason', 'Need more seats')
            ->call('submitPlanRequest')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tenant_plan_change_requests', [
            'tenant_id' => $tenant->id,
            'requested_plan_id' => $plan->id,
            'status' => PlanChangeRequestStatus::Pending->value,
        ]);
    }

    public function test_super_admin_can_approve_plan_change_request(): void
    {
        $this->seed(PlansSeeder::class);
        $admin = User::factory()->platformSuperAdmin()->create();
        ['tenant' => $tenant, 'user' => $owner] = $this->createTenantWithMember(role: TenantRole::Owner);
        $plan = Plan::query()->where('code', 'starter')->firstOrFail();

        $request = app(PlanChangeRequestService::class)->submit($tenant, $owner, $plan, 'Upgrade please');

        $this->actingAs($admin);

        Volt::test('platform.plan-change-requests.index')
            ->call('approve', $request->id)
            ->assertHasNoErrors();

        $this->assertSame(PlanChangeRequestStatus::Approved, $request->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::PlanChangeApproved->value]);
    }

    public function test_public_password_reset_remains_generic(): void
    {
        Volt::test('auth.forgot-password')
            ->set('email', 'missing-user@example.test')
            ->call('sendPasswordResetLink')
            ->assertHasNoErrors()
            ->assertSee('A reset link will be sent if the account exists.', false);
    }

    public function test_super_admin_can_lookup_email(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        $user = User::factory()->create(['email' => 'lookup@example.test']);

        $this->actingAs($admin);

        Volt::test('platform.account-lookup')
            ->set('email', 'lookup@example.test')
            ->call('lookup')
            ->assertSet('result.exists', true)
            ->assertSee('lookup@example.test', false);
    }

    public function test_tenant_admin_cannot_access_account_lookup(): void
    {
        ['tenant' => $tenant, 'user' => $admin] = $this->createTenantWithMember(role: TenantRole::Admin);

        $this->actingAs($admin)
            ->get(route('platform.account-lookup'))
            ->assertForbidden();
    }
}
