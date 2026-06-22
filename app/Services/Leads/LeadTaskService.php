<?php

namespace App\Services\Leads;

use App\Enums\Billing\PlanFeature;
use App\Enums\Leads\LeadActivityType;
use App\Enums\Leads\LeadTaskPriority;
use App\Enums\Leads\LeadTaskStatus;
use App\Enums\Leads\LeadTaskType;
use App\Enums\Tenancy\TenantRole;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\LeadTask;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\EntitlementResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadTaskService
{
    public function __construct(
        private readonly LeadActivityLogger $activity,
        private readonly EntitlementResolver $entitlements,
        private readonly LeadAssignmentService $assignments,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function createForLead(Lead $lead, User $actor, array $input, bool $adminContext = false): LeadTask
    {
        if ($adminContext) {
            $this->entitlements->assertAllowed($lead->tenant, PlanFeature::LeadManagement);
        } else {
            $this->entitlements->assertAllowed($lead->tenant, PlanFeature::CounsellorWorkspace);
        }

        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'Task title is required.']);
        }

        $assigneeId = $input['assigned_to_user_id'] ?? $lead->assigned_to;
        if ($assigneeId === null && $adminContext) {
            throw ValidationException::withMessages(['assigned_to_user_id' => 'Assign a counsellor or assign the lead before creating a task.']);
        }

        $assigneeId ??= $actor->id;
        $assignee = User::query()->findOrFail((int) $assigneeId);
        $this->assertTenantCounsellor($lead->tenant, $assignee);

        return DB::transaction(function () use ($lead, $actor, $input, $title, $assignee): LeadTask {
            $task = LeadTask::query()->create([
                'tenant_id' => $lead->tenant_id,
                'lead_id' => $lead->id,
                'assigned_to_user_id' => $assignee->id,
                'created_by_user_id' => $actor->id,
                'title' => $title,
                'description' => filled($input['description'] ?? null) ? trim((string) $input['description']) : null,
                'task_type' => $input['task_type'] ?? LeadTaskType::Counselling->value,
                'priority' => $input['priority'] ?? LeadTaskPriority::Normal->value,
                'status' => LeadTaskStatus::Pending->value,
                'due_at' => isset($input['due_at']) ? now()->parse($input['due_at']) : null,
                'metadata' => is_array($input['metadata'] ?? null) ? $input['metadata'] : null,
            ]);

            if ($lead->assigned_to === null) {
                $this->assignments->assign($lead, $assignee, $actor, 'Assigned via follow-up task');
                $lead->refresh();
            }

            $this->syncLeadNextFollowUp($lead);
            $this->activity->log(
                $lead,
                LeadActivityType::TaskCreated,
                $actor,
                ['task_id' => $task->id],
                title: $title,
                description: $task->description,
            );

            return $task->fresh(['lead', 'assignee']);
        });
    }

    public function start(LeadTask $task, User $actor): LeadTask
    {
        $this->entitlements->assertAllowed($this->tenantForTask($task), PlanFeature::CounsellorWorkspace);

        if (! in_array($task->status, [LeadTaskStatus::Pending, LeadTaskStatus::Overdue], true)) {
            throw ValidationException::withMessages(['status' => 'Only pending tasks can be started.']);
        }

        $task->update(['status' => LeadTaskStatus::InProgress]);

        $this->activity->log(
            $task->lead,
            LeadActivityType::TaskStarted,
            $actor,
            ['task_id' => $task->id],
            title: $task->title,
        );

        return $task->fresh();
    }

    public function complete(LeadTask $task, User $actor, ?string $note = null): LeadTask
    {
        $this->entitlements->assertAllowed($this->tenantForTask($task), PlanFeature::CounsellorWorkspace);

        if ($task->status === LeadTaskStatus::Completed) {
            return $task;
        }

        return DB::transaction(function () use ($task, $actor, $note): LeadTask {
            $task->update([
                'status' => LeadTaskStatus::Completed,
                'completed_at' => now(),
            ]);

            $this->syncLeadNextFollowUp($task->lead);
            $this->activity->log(
                $task->lead,
                LeadActivityType::TaskCompleted,
                $actor,
                array_filter(['task_id' => $task->id, 'note' => $note]),
                title: $task->title,
                description: $note,
            );

            return $task->fresh();
        });
    }

    public function cancel(LeadTask $task, User $actor, ?string $reason = null): LeadTask
    {
        $this->entitlements->assertAllowed($this->tenantForTask($task), PlanFeature::LeadManagement);

        $task->update(['status' => LeadTaskStatus::Cancelled]);
        $this->syncLeadNextFollowUp($task->lead);
        $this->activity->log(
            $task->lead,
            LeadActivityType::TaskCancelled,
            $actor,
            array_filter(['task_id' => $task->id, 'reason' => $reason]),
            title: $task->title,
            description: $reason,
        );

        return $task->fresh();
    }

    public function createForHandoff(Lead $lead, Conversation $conversation, ?User $assignee = null): ?LeadTask
    {
        if (! $this->entitlements->check($lead->tenant, PlanFeature::LeadManagement)->isAllowed()) {
            return null;
        }

        $handoffUuid = $conversation->handoff_request_uuid;
        if ($handoffUuid !== null) {
            $exists = LeadTask::query()
                ->where('tenant_id', $lead->tenant_id)
                ->where('lead_id', $lead->id)
                ->where('metadata->handoff_request_uuid', $handoffUuid)
                ->exists();

            if ($exists) {
                return null;
            }
        }

        $title = $this->handoffTaskTitle($lead);
        $assignee ??= $lead->assignee;
        $assigneeId = $assignee?->id ?? $conversation->target_counsellor_id;

        return DB::transaction(function () use ($lead, $conversation, $handoffUuid, $title, $assigneeId): LeadTask {
            $task = LeadTask::query()->create([
                'tenant_id' => $lead->tenant_id,
                'lead_id' => $lead->id,
                'assigned_to_user_id' => $assigneeId,
                'created_by_user_id' => null,
                'title' => $title,
                'description' => 'Automatic follow-up after visitor requested human counsellor support.',
                'task_type' => LeadTaskType::Counselling->value,
                'priority' => LeadTaskPriority::High->value,
                'status' => LeadTaskStatus::Pending->value,
                'due_at' => now(),
                'metadata' => array_filter([
                    'handoff_request_uuid' => $handoffUuid,
                    'conversation_uuid' => $conversation->uuid,
                    'source' => 'handoff',
                ]),
            ]);

            $this->syncLeadNextFollowUp($lead);
            $this->activity->log(
                $lead,
                LeadActivityType::HumanHandoffRequested,
                null,
                ['task_id' => $task->id, 'conversation_uuid' => $conversation->uuid],
                title: 'Human counsellor requested',
                description: $title,
            );
            $this->activity->log(
                $lead,
                LeadActivityType::TaskCreated,
                null,
                ['task_id' => $task->id, 'source' => 'handoff'],
                title: $title,
                description: $task->description,
            );

            return $task;
        });
    }

    private function handoffTaskTitle(Lead $lead): string
    {
        $interest = trim((string) ($lead->programme_interest ?? $lead->service_interest ?? ''));

        if ($interest !== '') {
            return 'Follow up with '.$interest.' enquiry';
        }

        return 'Follow up with counselling enquiry';
    }

    private function syncLeadNextFollowUp(Lead $lead): void
    {
        $nextDue = LeadTask::query()
            ->where('tenant_id', $lead->tenant_id)
            ->where('lead_id', $lead->id)
            ->whereIn('status', [
                LeadTaskStatus::Pending->value,
                LeadTaskStatus::InProgress->value,
            ])
            ->whereNotNull('due_at')
            ->orderBy('due_at')
            ->value('due_at');

        $lead->update(['next_follow_up_at' => $nextDue]);
    }

    private function assertTenantCounsellor(Tenant $tenant, User $user): void
    {
        if ($user->tenantRoleFor($tenant) !== TenantRole::Staff) {
            throw ValidationException::withMessages(['assigned_to_user_id' => 'Assignee must be an active counsellor in this tenant.']);
        }

        if (! $user->hasActiveMembership($tenant)) {
            throw ValidationException::withMessages(['assigned_to_user_id' => 'Assignee is not active in this tenant.']);
        }
    }

    private function tenantForTask(LeadTask $task): Tenant
    {
        if ($task->relationLoaded('tenant') && $task->tenant !== null) {
            return $task->tenant;
        }

        return Tenant::query()->findOrFail($task->tenant_id);
    }
}
