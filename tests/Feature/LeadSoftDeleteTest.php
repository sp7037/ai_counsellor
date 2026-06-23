<?php

namespace Tests\Feature;

use App\Enums\Audit\AuditAction;
use App\Enums\Leads\LeadSource;
use App\Enums\Tenancy\TenantRole;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Leads\LeadCreationService;
use App\Services\Leads\LeadLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class LeadSoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_admin_can_soft_delete_own_tenant_lead(): void
    {
        ['tenant' => $tenant, 'user' => $admin] = $this->createTenantWithMember(role: TenantRole::Admin);
        $lead = $this->createLead($tenant, $admin);

        $this->actingAs($admin);

        Volt::test('tenant.leads.show', ['tenant' => $tenant, 'lead' => $lead])
            ->set('confirm_delete', true)
            ->set('delete_reason', 'Test duplicate')
            ->call('deleteLead')
            ->assertHasNoErrors()
            ->assertRedirect(route('tenant.leads.index', $tenant));

        $this->assertSoftDeleted('leads', ['id' => $lead->id]);
        $lead->refresh();
        $this->assertSame($admin->id, $lead->deleted_by);
        $this->assertSame('Test duplicate', $lead->delete_reason);
    }

    public function test_tenant_admin_cannot_delete_another_tenants_lead(): void
    {
        ['tenant' => $tenantA, 'user' => $adminA] = $this->createTenantWithMember(role: TenantRole::Admin);
        ['tenant' => $tenantB, 'user' => $adminB] = $this->createTenantWithMember(role: TenantRole::Admin);
        $leadB = $this->createLead($tenantB, $adminB);

        $this->actingAs($adminA);

        $this->get(route('tenant.leads.show', [$tenantB, $leadB]))
            ->assertForbidden();
    }

    public function test_deleted_lead_disappears_from_default_list(): void
    {
        ['tenant' => $tenant, 'user' => $admin] = $this->createTenantWithMember(role: TenantRole::Admin);
        $lead = $this->createLead($tenant, $admin, ['full_name' => 'Delete Me Lead']);

        app(LeadLifecycleService::class)->softDelete($lead, $admin, 'Cleanup');

        $this->actingAs($admin);

        Volt::test('tenant.leads.index', ['tenant' => $tenant])
            ->assertDontSee('Delete Me Lead');

        Volt::test('tenant.leads.index', ['tenant' => $tenant])
            ->set('visibility', 'deleted')
            ->assertSee($lead->public_reference);
    }

    public function test_deleted_lead_can_be_restored(): void
    {
        ['tenant' => $tenant, 'user' => $admin] = $this->createTenantWithMember(role: TenantRole::Admin);
        $lead = $this->createLead($tenant, $admin);

        app(LeadLifecycleService::class)->softDelete($lead, $admin, 'Temporary');

        $this->actingAs($admin);

        Volt::test('tenant.leads.index', ['tenant' => $tenant])
            ->set('visibility', 'deleted')
            ->call('restoreLead', $lead->uuid)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'deleted_at' => null,
            'deleted_by' => null,
        ]);
    }

    public function test_non_authorized_user_cannot_delete_lead(): void
    {
        ['tenant' => $tenant, 'user' => $admin] = $this->createTenantWithMember(role: TenantRole::Admin);
        $staff = User::factory()->create();
        TenantMembership::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $staff->id,
            'role' => TenantRole::Staff->value,
            'status' => 'active',
        ]);

        $lead = $this->createLead($tenant, $admin);
        $lead->update(['assigned_to' => $staff->id]);

        $this->actingAs($staff);

        Volt::test('tenant.leads.show', ['tenant' => $tenant, 'lead' => $lead->fresh()])
            ->set('confirm_delete', true)
            ->call('deleteLead')
            ->assertForbidden();
    }

    public function test_lead_delete_and_restore_write_audit_logs(): void
    {
        ['tenant' => $tenant, 'user' => $admin] = $this->createTenantWithMember(role: TenantRole::Admin);
        $lead = $this->createLead($tenant, $admin);
        $service = app(LeadLifecycleService::class);

        $service->softDelete($lead, $admin, 'Audit test');

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::LeadDeleted->value,
            'tenant_id' => $tenant->id,
            'actor_user_id' => $admin->id,
        ]);

        $service->restore(Lead::withTrashed()->findOrFail($lead->id), $admin);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::LeadRestored->value,
            'tenant_id' => $tenant->id,
            'actor_user_id' => $admin->id,
        ]);
    }

    private function createLead(Tenant $tenant, User $actor, array $overrides = []): Lead
    {
        $this->withTenantContext($actor, $tenant);

        return app(LeadCreationService::class)->create($tenant, LeadSource::Manual, array_merge([
            'full_name' => 'Test Lead',
            'mobile' => '9'.str_pad((string) random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT),
            'enquiry_summary' => 'Test enquiry with enough detail for scoring.',
        ], $overrides), $actor);
    }
}
