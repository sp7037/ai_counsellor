<?php

namespace App\Services\Leads;

use App\Enums\Audit\AuditAction;
use App\Enums\Leads\FollowUpStatus;
use App\Enums\Leads\LeadActivityType;
use App\Enums\Leads\LeadPriority;
use App\Enums\Leads\LeadQualificationStatus;
use App\Enums\Leads\LeadStage;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\LeadNote;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadWorkflowService
{
    public function __construct(
        private readonly LeadTransitionService $transitions,
        private readonly LeadActivityLogger $activity,
        private readonly AuditLogger $audit,
    ) {}

    public function changeStage(Lead $lead, LeadStage $stage, User $actor, bool $adminOverride = false, ?string $reason = null): Lead
    {
        $this->transitions->assertCanTransition($lead, $stage, $actor, $adminOverride);

        return $this->updateLead($lead, $actor, LeadActivityType::StageChanged, [
            'stage' => $stage,
        ], [
            'stage' => $lead->stage->value,
        ], [
            'stage' => $stage->value,
            'reason' => $reason,
        ], $adminOverride ? AuditAction::LeadAdminOverride : AuditAction::LeadStageChanged);
    }

    public function recordContactAttempt(Lead $lead, User $actor): Lead
    {
        return $this->changeStage($lead, LeadStage::ContactAttempted, $actor);
    }

    public function markContacted(Lead $lead, User $actor): Lead
    {
        return DB::transaction(function () use ($lead, $actor): Lead {
            $lead = $this->changeStage($lead, LeadStage::Contacted, $actor);
            $lead->update(['last_contacted_at' => now()]);

            return $lead->fresh();
        });
    }

    public function markLost(Lead $lead, User $actor, string $reason): Lead
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required.']);
        }

        return DB::transaction(function () use ($lead, $actor, $reason): Lead {
            $lead->update([
                'stage' => LeadStage::Lost,
                'lost_reason' => $reason,
                'closed_at' => now(),
            ]);

            $this->activity->log($lead, LeadActivityType::MarkedLost, $actor, ['reason' => $reason]);
            $this->audit->log(AuditAction::LeadMarkedLost, $lead, $lead->tenant_id, ['reason' => $reason], $actor);

            return $lead->fresh();
        });
    }

    public function markInvalid(Lead $lead, User $actor, string $reason): Lead
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required.']);
        }

        return DB::transaction(function () use ($lead, $actor, $reason): Lead {
            $lead->update([
                'stage' => LeadStage::Invalid,
                'invalid_reason' => $reason,
                'closed_at' => now(),
            ]);

            $this->activity->log($lead, LeadActivityType::MarkedInvalid, $actor, ['reason' => $reason]);
            $this->audit->log(AuditAction::LeadMarkedInvalid, $lead, $lead->tenant_id, ['reason' => $reason], $actor);

            return $lead->fresh();
        });
    }

    public function markConverted(Lead $lead, User $actor): Lead
    {
        return DB::transaction(function () use ($lead, $actor): Lead {
            $lead->update([
                'stage' => LeadStage::Converted,
                'closed_at' => now(),
            ]);

            $this->activity->log($lead, LeadActivityType::Converted, $actor);
            $this->audit->log(AuditAction::LeadConverted, $lead, $lead->tenant_id, [], $actor);

            return $lead->fresh();
        });
    }

    public function reopen(Lead $lead, User $actor, string $reason): Lead
    {
        if (! $this->transitions->canReopen($lead, $actor)) {
            throw ValidationException::withMessages(['stage' => 'This lead cannot be reopened.']);
        }

        return DB::transaction(function () use ($lead, $actor, $reason): Lead {
            $lead->update([
                'stage' => LeadStage::Assigned,
                'closed_at' => null,
                'lost_reason' => null,
                'invalid_reason' => null,
            ]);

            $this->activity->log($lead, LeadActivityType::Reopened, $actor, ['reason' => $reason]);
            $this->audit->log(AuditAction::LeadReopened, $lead, $lead->tenant_id, ['reason' => $reason], $actor);

            return $lead->fresh();
        });
    }

    public function updateQualification(Lead $lead, LeadQualificationStatus $status, User $actor): Lead
    {
        return $this->updateLead($lead, $actor, LeadActivityType::QualificationUpdated, [
            'qualification_status' => $status,
        ], [
            'qualification_status' => $lead->qualification_status->value,
        ], [
            'qualification_status' => $status->value,
        ], AuditAction::LeadQualificationChanged);
    }

    public function updatePriority(Lead $lead, LeadPriority $priority, User $actor): Lead
    {
        return $this->updateLead($lead, $actor, LeadActivityType::PriorityChanged, [
            'priority' => $priority,
        ], [
            'priority' => $lead->priority->value,
        ], [
            'priority' => $priority->value,
        ], AuditAction::LeadPriorityChanged);
    }

    public function addNote(Lead $lead, User $actor, string $body): LeadNote
    {
        $body = trim($body);
        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'Note cannot be empty.']);
        }

        $note = LeadNote::query()->create([
            'tenant_id' => $lead->tenant_id,
            'lead_id' => $lead->id,
            'author_user_id' => $actor->id,
            'body' => $body,
        ]);

        $this->activity->log($lead, LeadActivityType::NoteAdded, $actor, [
            'note_id' => $note->id,
        ]);

        return $note;
    }

    public function scheduleFollowUp(Lead $lead, User $actor, \DateTimeInterface $dueAt, ?string $note = null): LeadFollowUp
    {
        $followUp = LeadFollowUp::query()->create([
            'tenant_id' => $lead->tenant_id,
            'lead_id' => $lead->id,
            'assigned_to' => $lead->assigned_to ?? $actor->id,
            'due_at' => $dueAt,
            'status' => FollowUpStatus::Scheduled,
            'note' => $note,
            'created_by' => $actor->id,
        ]);

        $lead->update(['next_follow_up_at' => $dueAt]);

        $this->activity->log($lead, LeadActivityType::FollowUpScheduled, $actor, [
            'follow_up_id' => $followUp->id,
            'due_at' => $dueAt->format(\DateTimeInterface::ATOM),
        ]);
        $this->audit->log(AuditAction::LeadFollowUpCreated, $followUp, $lead->tenant_id, [], $actor);

        return $followUp;
    }

    /**
     * @param  array<string, mixed>  $updates
     * @param  array<string, mixed>|null  $previous
     * @param  array<string, mixed>|null  $new
     */
    private function updateLead(
        Lead $lead,
        User $actor,
        LeadActivityType $activityType,
        array $updates,
        ?array $previous,
        ?array $new,
        AuditAction $auditAction,
    ): Lead {
        return DB::transaction(function () use ($lead, $actor, $activityType, $updates, $previous, $new, $auditAction): Lead {
            $lead->update($updates);
            $this->activity->log($lead, $activityType, $actor, [], $previous, $new);
            $this->audit->log($auditAction, $lead, $lead->tenant_id, $new ?? [], $actor);

            return $lead->fresh();
        });
    }
}
