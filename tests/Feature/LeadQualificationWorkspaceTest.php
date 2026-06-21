<?php

namespace Tests\Feature;

use App\Enums\Leads\LeadSource;
use App\Enums\Tenancy\TenantRole;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Auth\PostLoginRedirect;
use App\Services\Leads\CounsellorManagementService;
use App\Services\Leads\LeadAssignmentService;
use App\Services\Leads\LeadCreationService;
use App\Services\Leads\LeadQualificationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LeadQualificationWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_super_admin_denied_from_tenant_leads_and_workspace(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        ['tenant' => $tenant] = $this->createTenantWithMember();

        $this->actingAs($admin)->get(route('tenant.leads.index', $tenant))->assertForbidden();
        $this->actingAs($admin)->get(route('workspace.dashboard', $tenant))->assertForbidden();
    }

    public function test_tenant_admin_can_access_lead_management(): void
    {
        ['tenant' => $tenant, 'user' => $admin] = $this->createTenantWithMember(role: TenantRole::Admin);

        $this->actingAs($admin)->get(route('tenant.leads.index', $tenant))->assertOk();
        $this->actingAs($admin)->get(route('tenant.counsellors.index', $tenant))->assertOk();
    }

    public function test_counsellor_denied_tenant_lead_admin_routes(): void
    {
        ['tenant' => $tenant, 'user' => $counsellor] = $this->createTenantWithMember(role: TenantRole::Staff);

        $this->actingAs($counsellor)->get(route('tenant.leads.index', $tenant))->assertForbidden();
    }

    public function test_counsellor_can_access_workspace(): void
    {
        ['tenant' => $tenant, 'user' => $counsellor] = $this->createTenantWithMember(role: TenantRole::Staff);

        $this->actingAs($counsellor)->get(route('workspace.dashboard', $tenant))->assertOk();
        $this->actingAs($counsellor)->get(route('workspace.leads.index', $tenant))->assertOk();
    }

    public function test_tenant_admin_can_create_counsellor_profile_with_tenant_id(): void
    {
        ['tenant' => $tenant, 'user' => $admin] = $this->createTenantWithMember(role: TenantRole::Admin);
        $this->actingAs($admin);
        $this->withTenantContext($admin, $tenant);

        $membership = app(CounsellorManagementService::class)->create(
            $tenant,
            [
                'name' => 'Riya Verma',
                'email' => 'riya.counsellor@example.test',
                'password' => 'temporary-password-123',
            ],
            [
                'mobile' => '9898989898',
                'designation' => 'Senior Counsellor',
            ],
            $admin,
        );

        $this->assertDatabaseHas('counsellor_profiles', [
            'membership_id' => $membership->id,
            'tenant_id' => $tenant->id,
            'designation' => 'Senior Counsellor',
        ]);
    }

    public function test_counsellor_login_redirects_to_workspace(): void
    {
        ['tenant' => $tenant, 'user' => $counsellor] = $this->createTenantWithMember(role: TenantRole::Staff);

        $this->assertSame(
            route('workspace.dashboard', $tenant),
            app(PostLoginRedirect::class)->intendedUrl($counsellor),
        );
    }

    public function test_manual_lead_creation_and_qualification_score(): void
    {
        ['tenant' => $tenant, 'user' => $admin] = $this->createTenantWithMember(role: TenantRole::Admin);
        $this->withTenantContext($admin, $tenant);

        $lead = app(LeadCreationService::class)->create($tenant, LeadSource::Manual, [
            'full_name' => 'Priya Sharma',
            'mobile' => '9876543210',
            'email' => 'priya@example.test',
            'service_interest' => 'Study abroad',
            'enquiry_summary' => 'Interested in UK masters programme for September intake.',
            'requested_human_contact' => true,
        ], $admin);

        $this->assertGreaterThan(0, $lead->lead_score);
        $this->assertNotEmpty($lead->score_components);
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'tenant_id' => $tenant->id]);
    }

    public function test_lead_capture_idempotency_by_capture_event_uuid(): void
    {
        $result = $this->createWidgetReadyTenant();
        $token = $this->widgetSessionToken($result['key']);
        $uuid = (string) Str::uuid();

        $first = $this->postJson('/widget/v1/leads', [
            'full_name' => 'Widget User',
            'mobile' => '9123456789',
            'enquiry_summary' => 'Need counselling',
            'capture_event_uuid' => $uuid,
        ], [
            'Authorization' => 'Bearer '.$token,
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertOk();

        $second = $this->postJson('/widget/v1/leads', [
            'full_name' => 'Widget User',
            'mobile' => '9123456789',
            'enquiry_summary' => 'Need counselling',
            'capture_event_uuid' => $uuid,
        ], [
            'Authorization' => 'Bearer '.$token,
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertOk();

        $this->assertSame($first->json('lead_reference'), $second->json('lead_reference'));
        $this->assertSame(1, Lead::withoutGlobalScopes()->where('tenant_id', $result['tenant']->id)->count());
    }

    public function test_widget_lead_response_does_not_expose_internal_notes(): void
    {
        $result = $this->createWidgetReadyTenant();
        $token = $this->widgetSessionToken($result['key']);

        $response = $this->postJson('/widget/v1/leads', [
            'full_name' => 'Visitor',
            'capture_event_uuid' => (string) Str::uuid(),
        ], [
            'Authorization' => 'Bearer '.$token,
            'Origin' => 'http://127.0.0.1:8000',
        ]);

        $response->assertOk()->assertJsonMissing(['notes', 'assigned_to', 'audit']);
    }

    public function test_assignment_and_counsellor_isolation(): void
    {
        ['tenant' => $tenant, 'user' => $admin] = $this->createTenantWithMember(role: TenantRole::Admin);
        ['user' => $counsellorA] = $this->createTenantWithMember(role: TenantRole::Staff);
        // Re-attach counsellor A to the same tenant as admin
        $counsellorA->memberships()->delete();
        TenantMembership::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $counsellorA->id,
            'role' => TenantRole::Staff->value,
        ]);
        ['tenant' => $tenantB, 'user' => $counsellorB] = $this->createTenantWithMember(role: TenantRole::Staff);

        $lead = $this->createLead($tenant, $admin);

        $this->expectException(ValidationException::class);
        app(LeadAssignmentService::class)->assign($lead, $counsellorB, $admin);

        $lead = app(LeadAssignmentService::class)->assign($lead, $counsellorA, $admin);
        $this->assertSame($counsellorA->id, $lead->assigned_to);

        $this->actingAs($counsellorA)->get(route('workspace.leads.show', [$tenant, $lead]))->assertOk();
        $this->actingAs($counsellorB)->get(route('workspace.leads.show', [$tenantB, $lead]))->assertForbidden();
    }

    public function test_cross_tenant_lead_access_denied(): void
    {
        ['tenant' => $tenantA, 'user' => $adminA] = $this->createTenantWithMember(role: TenantRole::Admin);
        ['tenant' => $tenantB, 'user' => $adminB] = $this->createTenantWithMember(role: TenantRole::Admin);

        $lead = $this->createLead($tenantA, $adminA);

        $this->actingAs($adminB)->get(route('tenant.leads.show', [$tenantA, $lead]))->assertForbidden();
    }

    public function test_deterministic_qualification_does_not_use_sensitive_attributes(): void
    {
        ['tenant' => $tenant] = $this->createTenantWithMember();

        $result = app(LeadQualificationEngine::class)->score($tenant, [
            'full_name' => 'Test',
            'religion' => 'should-not-score',
            'gender' => 'should-not-score',
        ]);

        $this->assertArrayNotHasKey('religion', $result['components']);
        $this->assertArrayNotHasKey('gender', $result['components']);
    }

    public function test_suspend_tenant_blocks_widget_lead_capture(): void
    {
        $result = $this->createWidgetReadyTenant();
        $token = $this->widgetSessionToken($result['key']);
        $result['tenant']->update(['status' => 'suspended', 'suspended_at' => now(), 'suspension_reason' => 'Test']);

        $this->postJson('/widget/v1/leads', [
            'full_name' => 'Visitor',
            'capture_event_uuid' => (string) Str::uuid(),
        ], [
            'Authorization' => 'Bearer '.$token,
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertForbidden();
    }

    private function createLead(Tenant $tenant, User $actor): Lead
    {
        $this->withTenantContext($actor, $tenant);

        return app(LeadCreationService::class)->create($tenant, LeadSource::Manual, [
            'full_name' => 'Test Lead',
            'mobile' => '9000000001',
            'enquiry_summary' => 'Test enquiry with enough detail for scoring.',
        ], $actor);
    }
}
