<?php

namespace Tests\Feature;

use App\Enums\Billing\PlanFeature;
use App\Enums\Leads\LeadActivityType;
use App\Enums\Leads\LeadSource;
use App\Enums\Leads\LeadTaskStatus;
use App\Enums\Tenancy\TenantRole;
use App\Exceptions\Billing\EntitlementDeniedException;
use App\Models\Lead;
use App\Models\LeadTask;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Billing\EntitlementOverrideService;
use App\Services\Leads\LeadAssignmentService;
use App\Services\Leads\LeadCreationService;
use App\Services\Leads\LeadTaskDirectoryService;
use App\Services\Leads\LeadTaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LeadTaskFollowUpTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_assign_lead_to_counsellor_in_same_tenant(): void
    {
        ['tenant' => $tenant, 'user' => $admin] = $this->createTenantWithMember(role: TenantRole::Admin);
        ['user' => $counsellor] = $this->createTenantWithMember(role: TenantRole::Staff);
        $counsellor->memberships()->delete();
        TenantMembership::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $counsellor->id,
            'role' => TenantRole::Staff->value,
        ]);

        $lead = $this->createLead($tenant, $admin);
        $lead = app(LeadAssignmentService::class)->assign($lead, $counsellor, $admin);

        $this->assertSame($counsellor->id, $lead->assigned_to);
    }

    public function test_cannot_assign_lead_to_user_from_another_tenant(): void
    {
        ['tenant' => $tenant, 'user' => $admin] = $this->createTenantWithMember(role: TenantRole::Admin);
        ['tenant' => $tenantB, 'user' => $counsellorB] = $this->createTenantWithMember(role: TenantRole::Staff);
        $lead = $this->createLead($tenant, $admin);

        $this->expectException(ValidationException::class);
        app(LeadAssignmentService::class)->assign($lead, $counsellorB, $admin);
    }

    public function test_admin_can_create_follow_up_task(): void
    {
        ['tenant' => $tenant, 'user' => $admin] = $this->createTenantWithMember(role: TenantRole::Admin);
        ['user' => $counsellor] = $this->createTenantWithMember(role: TenantRole::Staff);
        $counsellor->memberships()->delete();
        TenantMembership::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $counsellor->id,
            'role' => TenantRole::Staff->value,
        ]);

        $lead = $this->createLead($tenant, $admin);
        $lead = app(LeadAssignmentService::class)->assign($lead, $counsellor, $admin);

        $task = app(LeadTaskService::class)->createForLead($lead, $admin, [
            'title' => 'Call back about MBBS options',
            'due_at' => now()->addDay()->toIso8601String(),
            'task_type' => 'call',
            'priority' => 'high',
            'assigned_to_user_id' => $counsellor->id,
        ], adminContext: true);

        $this->assertDatabaseHas('lead_tasks', [
            'id' => $task->id,
            'lead_id' => $lead->id,
            'title' => 'Call back about MBBS options',
            'status' => LeadTaskStatus::Pending->value,
        ]);
    }

    public function test_counsellor_can_complete_task_and_activity_is_logged(): void
    {
        ['tenant' => $tenant, 'user' => $admin] = $this->createTenantWithMember(role: TenantRole::Admin);
        ['user' => $counsellor] = $this->createTenantWithMember(role: TenantRole::Staff);
        $counsellor->memberships()->delete();
        TenantMembership::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $counsellor->id,
            'role' => TenantRole::Staff->value,
        ]);

        $lead = $this->createLead($tenant, $admin);
        $lead = app(LeadAssignmentService::class)->assign($lead, $counsellor, $admin);

        $task = app(LeadTaskService::class)->createForLead($lead, $admin, [
            'title' => 'WhatsApp follow-up',
            'assigned_to_user_id' => $counsellor->id,
            'due_at' => now()->addHours(2)->toIso8601String(),
        ], adminContext: true);

        app(LeadTaskService::class)->complete($task, $counsellor, 'Spoke with parent');

        $this->assertSame(LeadTaskStatus::Completed, $task->fresh()->status);
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'action_type' => LeadActivityType::TaskCompleted->value,
        ]);
    }

    public function test_counsellor_sees_assigned_task_but_not_other_counsellor_task(): void
    {
        ['tenant' => $tenant, 'user' => $admin] = $this->createTenantWithMember(role: TenantRole::Admin);
        ['user' => $counsellorA] = $this->createTenantWithMember(role: TenantRole::Staff);
        ['user' => $counsellorB] = $this->createTenantWithMember(role: TenantRole::Staff);
        foreach ([$counsellorA, $counsellorB] as $counsellor) {
            $counsellor->memberships()->delete();
            TenantMembership::factory()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $counsellor->id,
                'role' => TenantRole::Staff->value,
            ]);
        }

        $leadA = $this->createLead($tenant, $admin);
        $leadB = $this->createLead($tenant, $admin);
        $leadA = app(LeadAssignmentService::class)->assign($leadA, $counsellorA, $admin);
        $leadB = app(LeadAssignmentService::class)->assign($leadB, $counsellorB, $admin);

        app(LeadTaskService::class)->createForLead($leadA, $admin, [
            'title' => 'Task for A',
            'assigned_to_user_id' => $counsellorA->id,
        ], adminContext: true);
        app(LeadTaskService::class)->createForLead($leadB, $admin, [
            'title' => 'Task for B',
            'assigned_to_user_id' => $counsellorB->id,
        ], adminContext: true);

        $tasksA = app(LeadTaskDirectoryService::class)->listForCounsellor($tenant, $counsellorA);
        $tasksB = app(LeadTaskDirectoryService::class)->listForCounsellor($tenant, $counsellorB);

        $this->assertCount(1, $tasksA);
        $this->assertCount(1, $tasksB);
        $this->assertSame('Task for A', $tasksA->first()->title);

        $this->actingAs($counsellorA)->get(route('workspace.leads.show', [$tenant, $leadA]))->assertOk();
        $this->actingAs($counsellorA)->get(route('workspace.leads.show', [$tenant, $leadB]))->assertForbidden();
    }

    public function test_handoff_creates_default_follow_up_task(): void
    {
        $widget = $this->createWidgetReadyTenant();
        $token = $this->widgetSessionToken($widget['key']);

        $this->postJson('/widget/v1/messages', [
            'body' => 'My name is Neha, mobile 9876501234. Can you guide me for MBBS abroad?',
            'request_id' => (string) Str::uuid(),
        ], $this->widgetHeaders($token))->assertOk();

        $uuid = (string) Str::uuid();
        $this->postJson('/widget/v1/handoff', [
            'handoff_request_uuid' => $uuid,
        ], $this->widgetHeaders($token))->assertOk();

        $lead = Lead::withoutGlobalScopes()->where('tenant_id', $widget['tenant']->id)->firstOrFail();

        $this->assertDatabaseHas('lead_tasks', [
            'tenant_id' => $widget['tenant']->id,
            'lead_id' => $lead->id,
        ]);

        $task = LeadTask::query()->where('lead_id', $lead->id)->first();
        $this->assertSame($uuid, $task->metadata['handoff_request_uuid'] ?? null);
    }

    public function test_duplicate_handoff_does_not_create_duplicate_task(): void
    {
        $widget = $this->createWidgetReadyTenant();
        $token = $this->widgetSessionToken($widget['key']);
        $uuid = (string) Str::uuid();

        $this->postJson('/widget/v1/handoff', ['handoff_request_uuid' => $uuid], $this->widgetHeaders($token))->assertOk();
        $this->postJson('/widget/v1/handoff', ['handoff_request_uuid' => $uuid], $this->widgetHeaders($token))->assertOk();

        $this->assertSame(1, LeadTask::withoutGlobalScopes()->count());
    }

    public function test_overdue_and_today_filters_work(): void
    {
        ['tenant' => $tenant, 'user' => $admin] = $this->createTenantWithMember(role: TenantRole::Admin);
        ['user' => $counsellor] = $this->createTenantWithMember(role: TenantRole::Staff);
        $counsellor->memberships()->delete();
        TenantMembership::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $counsellor->id,
            'role' => TenantRole::Staff->value,
        ]);

        $lead = $this->createLead($tenant, $admin);
        $lead = app(LeadAssignmentService::class)->assign($lead, $counsellor, $admin);

        LeadTask::query()->create([
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'assigned_to_user_id' => $counsellor->id,
            'created_by_user_id' => $admin->id,
            'title' => 'Overdue task',
            'task_type' => 'call',
            'priority' => 'normal',
            'status' => LeadTaskStatus::Pending->value,
            'due_at' => now()->subDay(),
        ]);

        LeadTask::query()->create([
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'assigned_to_user_id' => $counsellor->id,
            'created_by_user_id' => $admin->id,
            'title' => 'Today task',
            'task_type' => 'call',
            'priority' => 'normal',
            'status' => LeadTaskStatus::Pending->value,
            'due_at' => now()->startOfDay()->addHours(10),
        ]);

        $directory = app(LeadTaskDirectoryService::class);
        $this->assertCount(1, $directory->listForCounsellor($tenant, $counsellor, ['due_overdue' => true]));
        $this->assertCount(1, $directory->listForCounsellor($tenant, $counsellor, ['due_today' => true]));
    }

    public function test_lead_management_entitlement_blocks_admin_task_creation(): void
    {
        ['tenant' => $tenant, 'user' => $admin] = $this->createTenantWithMember(role: TenantRole::Admin);
        $lead = $this->createLead($tenant, $admin);

        app(EntitlementOverrideService::class)->apply(
            $tenant,
            PlanFeature::LeadManagement,
            false,
            null,
            $admin,
            'Test disable',
        );

        $this->expectException(EntitlementDeniedException::class);
        app(LeadTaskService::class)->createForLead($lead, $admin, [
            'title' => 'Blocked task',
        ], adminContext: true);
    }

    public function test_counsellor_workspace_entitlement_blocks_task_completion(): void
    {
        ['tenant' => $tenant, 'user' => $admin] = $this->createTenantWithMember(role: TenantRole::Admin);
        ['user' => $counsellor] = $this->createTenantWithMember(role: TenantRole::Staff);
        $counsellor->memberships()->delete();
        TenantMembership::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $counsellor->id,
            'role' => TenantRole::Staff->value,
        ]);

        $lead = $this->createLead($tenant, $admin);
        $task = app(LeadTaskService::class)->createForLead($lead, $admin, [
            'title' => 'Task',
            'assigned_to_user_id' => $counsellor->id,
        ], adminContext: true);

        app(EntitlementOverrideService::class)->apply(
            $tenant,
            PlanFeature::CounsellorWorkspace,
            false,
            null,
            $admin,
            'Test disable',
        );

        $this->expectException(EntitlementDeniedException::class);
        app(LeadTaskService::class)->complete($task, $counsellor);
    }

    public function test_tenant_isolation_for_tasks(): void
    {
        ['tenant' => $tenantA, 'user' => $adminA] = $this->createTenantWithMember(role: TenantRole::Admin);
        ['tenant' => $tenantB] = $this->createTenantWithMember(role: TenantRole::Admin);
        ['user' => $counsellorA] = $this->createTenantWithMember(role: TenantRole::Staff);
        $counsellorA->memberships()->delete();
        TenantMembership::factory()->create([
            'tenant_id' => $tenantA->id,
            'user_id' => $counsellorA->id,
            'role' => TenantRole::Staff->value,
        ]);

        $leadA = $this->createLead($tenantA, $adminA);
        $leadA = app(LeadAssignmentService::class)->assign($leadA, $counsellorA, $adminA);
        app(LeadTaskService::class)->createForLead($leadA, $adminA, [
            'title' => 'Tenant A task',
            'assigned_to_user_id' => $counsellorA->id,
        ], adminContext: true);

        $this->assertSame(1, LeadTask::withoutGlobalScopes()->where('tenant_id', $tenantA->id)->count());
        $this->assertSame(0, LeadTask::withoutGlobalScopes()->where('tenant_id', $tenantB->id)->count());
    }

    public function test_workspace_follow_ups_page_renders_for_counsellor(): void
    {
        ['tenant' => $tenant, 'user' => $counsellor] = $this->createTenantWithMember(role: TenantRole::Staff);

        $this->actingAs($counsellor)->get(route('workspace.follow-ups.index', $tenant))->assertOk();
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

    /**
     * @return array<string, string>
     */
    private function widgetHeaders(string $token): array
    {
        return [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ];
    }
}
