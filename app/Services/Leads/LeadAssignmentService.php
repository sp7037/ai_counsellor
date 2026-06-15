<?php

namespace App\Services\Leads;

use App\Enums\Audit\AuditAction;
use App\Enums\Leads\LeadActivityType;
use App\Enums\Leads\LeadStage;
use App\Enums\Tenancy\MembershipStatus;
use App\Enums\Tenancy\TenantRole;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\LeadNotification;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadAssignmentService
{
    public function __construct(
        private readonly LeadActivityLogger $activity,
        private readonly AuditLogger $audit,
    ) {}

    public function assign(Lead $lead, User $counsellor, User $actor, ?string $note = null): Lead
    {
        $this->assertActiveCounsellor($lead->tenant, $counsellor);

        return DB::transaction(function () use ($lead, $counsellor, $actor, $note): Lead {
            $previousAssignee = $lead->assigned_to;

            LeadAssignment::query()
                ->where('lead_id', $lead->id)
                ->where('is_current', true)
                ->update([
                    'is_current' => false,
                    'unassigned_at' => now(),
                ]);

            LeadAssignment::query()->create([
                'tenant_id' => $lead->tenant_id,
                'lead_id' => $lead->id,
                'assigned_to' => $counsellor->id,
                'assigned_by' => $actor->id,
                'note' => $note,
                'is_current' => true,
                'assigned_at' => now(),
            ]);

            $lead->update([
                'assigned_to' => $counsellor->id,
                'assigned_at' => now(),
                'stage' => LeadStage::Assigned,
            ]);

            $type = $previousAssignee === null
                ? LeadActivityType::Assigned
                : LeadActivityType::Reassigned;

            $this->activity->log($lead, $type, $actor, [
                'note' => $note,
            ], [
                'assigned_to' => $previousAssignee,
            ], [
                'assigned_to' => $counsellor->id,
            ]);

            $this->audit->log(
                $previousAssignee === null ? AuditAction::LeadAssigned : AuditAction::LeadReassigned,
                $lead,
                $lead->tenant_id,
                ['assigned_to' => $counsellor->id],
                $actor,
            );

            LeadNotification::query()->create([
                'tenant_id' => $lead->tenant_id,
                'user_id' => $counsellor->id,
                'lead_id' => $lead->id,
                'type' => $type->value,
                'title' => $previousAssignee === null ? 'New lead assigned' : 'Lead reassigned to you',
                'body' => $lead->full_name.' ('.$lead->public_reference.')',
            ]);

            return $lead->fresh();
        });
    }

    public function assertActiveCounsellor(Tenant $tenant, User $counsellor): void
    {
        $membership = TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $counsellor->id)
            ->first();

        if ($membership === null
            || $membership->role !== TenantRole::Staff
            || $membership->status !== MembershipStatus::Active) {
            throw ValidationException::withMessages([
                'assigned_to' => 'Lead can only be assigned to an active counsellor.',
            ]);
        }
    }
}
