<?php

namespace Tests\Feature;

use App\Enums\Audit\AuditAction;
use App\Enums\Tenancy\TenantRole;
use App\Enums\Tenancy\TenantStatus;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TenantArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_cannot_archive_active_tenant(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        $tenant = Tenant::factory()->active()->create();

        $this->actingAs($admin);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Tenant must be suspended before it can be archived or deleted.');

        app(TenantLifecycleService::class)->archive($tenant, 'No longer needed', $admin);
    }

    public function test_super_admin_cannot_archive_active_tenant_via_ui(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        $tenant = Tenant::factory()->active()->create();

        $this->actingAs($admin);

        Volt::test('platform.tenants.show', ['tenant' => $tenant])
            ->assertDontSee('Archive tenant', false);
    }

    public function test_super_admin_can_archive_suspended_tenant(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        $tenant = Tenant::factory()->suspended('Billing hold')->create();

        $this->actingAs($admin);

        Volt::test('platform.tenants.show', ['tenant' => $tenant])
            ->set('confirm_archive', true)
            ->set('archive_reason', 'Client closed account')
            ->call('archive')
            ->assertHasNoErrors();

        $tenant->refresh();
        $this->assertSame(TenantStatus::Archived, $tenant->status);
        $this->assertSame('Client closed account', $tenant->archive_reason);
        $this->assertSame($admin->id, $tenant->archived_by);
        $this->assertNotNull($tenant->archived_at);
    }

    public function test_archived_tenant_hidden_from_default_tenant_list(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        Tenant::factory()->active()->create(['name' => 'Visible Org', 'slug' => 'visible-org']);
        Tenant::factory()->archived()->create(['name' => 'Archived Org', 'slug' => 'archived-org']);

        $this->actingAs($admin);

        Volt::test('platform.tenants.index')
            ->assertSee('Visible Org')
            ->assertDontSee('Archived Org');
    }

    public function test_archived_tenant_appears_in_archived_and_all_filters(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        Tenant::factory()->archived()->create(['name' => 'Archived Org', 'slug' => 'archived-org']);

        $this->actingAs($admin);

        Volt::test('platform.tenants.index')
            ->set('status', TenantStatus::Archived->value)
            ->assertSee('Archived Org');

        Volt::test('platform.tenants.index')
            ->set('status', 'all')
            ->assertSee('Archived Org');
    }

    public function test_archived_tenant_cannot_access_tenant_routes(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Owner);
        $admin = User::factory()->platformSuperAdmin()->create();

        app(TenantLifecycleService::class)->suspend($tenant, 'Pre-archive suspension', $admin);
        app(TenantLifecycleService::class)->archive($tenant->fresh(), 'Account closed', $admin);

        $this->actingAs($user)
            ->get(route('tenant.dashboard', $tenant->fresh()))
            ->assertForbidden();
    }

    public function test_archived_tenant_cannot_start_widget_session(): void
    {
        ['tenant' => $tenant, 'key' => $key] = $this->createWidgetReadyTenant();
        $admin = User::factory()->platformSuperAdmin()->create();

        app(TenantLifecycleService::class)->suspend($tenant, 'Pre-archive suspension', $admin);
        app(TenantLifecycleService::class)->archive($tenant->fresh(), 'Account closed', $admin);

        $this->postJson('/widget/v1/session', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertForbidden();
    }

    public function test_super_admin_can_restore_archived_tenant_to_suspended(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        $tenant = Tenant::factory()->suspended('Hold')->create();

        app(TenantLifecycleService::class)->archive($tenant, 'Temporary archive', $admin);

        $this->actingAs($admin);

        Volt::test('platform.tenants.show', ['tenant' => $tenant->fresh()])
            ->call('restore')
            ->assertHasNoErrors();

        $tenant->refresh();
        $this->assertSame(TenantStatus::Suspended, $tenant->status);
        $this->assertNull($tenant->archived_at);
        $this->assertNull($tenant->archived_by);
        $this->assertNull($tenant->archive_reason);
    }

    public function test_restore_does_not_reactivate_tenant(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        $tenant = Tenant::factory()->suspended('Hold')->create();

        app(TenantLifecycleService::class)->archive($tenant, 'Temporary archive', $admin);
        app(TenantLifecycleService::class)->restoreFromArchive($tenant->fresh(), $admin);

        $tenant->refresh();
        $this->assertNotSame(TenantStatus::Active, $tenant->status);
        $this->assertFalse($tenant->allowsTenantAccess());
    }

    public function test_non_super_admin_cannot_archive_tenant(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Owner);
        $tenant->update(['status' => TenantStatus::Suspended->value, 'suspended_at' => now()]);

        $this->actingAs($user)
            ->get(route('platform.tenants.show', $tenant->fresh()))
            ->assertForbidden();
    }

    public function test_archive_and_restore_write_audit_logs(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        $tenant = Tenant::factory()->suspended('Hold')->create();
        $service = app(TenantLifecycleService::class);

        $service->archive($tenant, 'Archive audit test', $admin);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::TenantArchived->value,
            'actor_user_id' => $admin->id,
        ]);

        $archiveLog = AuditLog::query()
            ->where('action', AuditAction::TenantArchived->value)
            ->latest('id')
            ->first();

        $this->assertSame($tenant->id, $archiveLog?->subject_id);

        $service->restoreFromArchive($tenant->fresh(), $admin);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::TenantRestored->value,
            'actor_user_id' => $admin->id,
        ]);
    }

    public function test_super_admin_can_soft_delete_archived_tenant(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        $tenant = Tenant::factory()->suspended('Hold')->create();
        app(TenantLifecycleService::class)->archive($tenant, 'Pre-delete archive', $admin);

        $this->actingAs($admin);

        Volt::test('platform.tenants.show', ['tenant' => $tenant->fresh()])
            ->set('confirm_delete', true)
            ->set('delete_reason', 'Customer requested removal')
            ->set('delete_confirmation', 'DELETE TENANT')
            ->call('deleteTenant')
            ->assertHasNoErrors();

        $tenant->refresh();
        $this->assertSame(TenantStatus::Deleted, $tenant->status);
        $this->assertSame('Customer requested removal', $tenant->delete_reason);
        $this->assertSame($admin->id, $tenant->deleted_by);
        $this->assertNotNull($tenant->deleted_at);
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id]);
    }

    public function test_deleted_tenant_hidden_from_default_tenant_list(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        Tenant::factory()->active()->create(['name' => 'Visible Org', 'slug' => 'visible-org-2']);
        Tenant::factory()->deleted()->create(['name' => 'Deleted Org', 'slug' => 'deleted-org']);

        $this->actingAs($admin);

        Volt::test('platform.tenants.index')
            ->assertSee('Visible Org')
            ->assertDontSee('Deleted Org');
    }

    public function test_deleted_tenant_appears_in_deleted_filter(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        Tenant::factory()->deleted()->create(['name' => 'Deleted Org', 'slug' => 'deleted-org-filter']);

        $this->actingAs($admin);

        Volt::test('platform.tenants.index')
            ->set('status', TenantStatus::Deleted->value)
            ->assertSee('Deleted Org');
    }

    public function test_deleted_tenant_cannot_access_tenant_routes_or_widget(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'key' => $key] = $this->createWidgetReadyTenant();
        $admin = User::factory()->platformSuperAdmin()->create();

        app(TenantLifecycleService::class)->suspend($tenant, 'Hold', $admin);
        app(TenantLifecycleService::class)->archive($tenant->fresh(), 'Archive', $admin);
        app(TenantLifecycleService::class)->softDelete($tenant->fresh(), 'Delete', $admin);

        $this->actingAs($user)
            ->get(route('tenant.dashboard', $tenant->fresh()))
            ->assertForbidden();

        $this->postJson('/widget/v1/session', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertForbidden();
    }

    public function test_super_admin_can_restore_deleted_tenant_to_suspended(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        $tenant = Tenant::factory()->deleted()->create();

        $this->actingAs($admin);

        Volt::test('platform.tenants.show', ['tenant' => $tenant])
            ->call('restore')
            ->assertHasNoErrors();

        $tenant->refresh();
        $this->assertSame(TenantStatus::Suspended, $tenant->status);
        $this->assertNull($tenant->deleted_at);
        $this->assertFalse($tenant->allowsTenantAccess());
    }

    public function test_tenant_delete_requires_archived_status(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        $tenant = Tenant::factory()->suspended('Hold')->create();

        $this->expectException(ValidationException::class);

        app(TenantLifecycleService::class)->deleteTenant($tenant, 'DELETE TENANT', 'Too early', $admin);
    }

    public function test_tenant_delete_requires_exact_confirmation(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        $tenant = Tenant::factory()->suspended('Hold')->create();
        app(TenantLifecycleService::class)->archive($tenant, 'Archive first', $admin);

        $this->actingAs($admin);

        Volt::test('platform.tenants.show', ['tenant' => $tenant->fresh()])
            ->set('confirm_delete', true)
            ->set('delete_reason', 'Wrong confirm')
            ->set('delete_confirmation', 'delete tenant')
            ->call('deleteTenant')
            ->assertHasErrors('delete_confirmation');

        $tenant->refresh();
        $this->assertSame(TenantStatus::Archived, $tenant->status);
    }

    public function test_tenant_soft_delete_writes_audit_logs(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        $tenant = Tenant::factory()->suspended('Hold')->create();
        app(TenantLifecycleService::class)->archive($tenant, 'Archive first', $admin);

        app(TenantLifecycleService::class)->deleteTenant($tenant->fresh(), 'DELETE TENANT', 'Final delete', $admin);

        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::TenantDeleteAttempted->value]);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::TenantDeleted->value]);
    }

    public function test_hard_permanent_delete_remains_blocked(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        $tenant = Tenant::factory()->deleted()->create();

        $this->expectException(ValidationException::class);

        app(TenantLifecycleService::class)->attemptPermanentDelete($tenant, 'DELETE TENANT', $admin);
    }

    public function test_permanent_delete_not_available_on_active_tenant(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        $tenant = Tenant::factory()->active()->create();

        $this->actingAs($admin);

        Volt::test('platform.tenants.show', ['tenant' => $tenant])
            ->assertDontSee('Delete tenant', false);
    }

    public function test_suspend_still_works_for_active_tenant(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        $tenant = Tenant::factory()->active()->create();

        $this->actingAs($admin);

        Volt::test('platform.tenants.show', ['tenant' => $tenant])
            ->set('confirm_suspend', true)
            ->set('suspension_reason', 'Billing dispute')
            ->call('suspend')
            ->assertHasNoErrors();

        $tenant->refresh();
        $this->assertSame(TenantStatus::Suspended, $tenant->status);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::TenantSuspended->value]);
    }
}
