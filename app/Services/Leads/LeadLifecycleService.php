<?php

namespace App\Services\Leads;

use App\Enums\Audit\AuditAction;
use App\Enums\Leads\LeadActivityType;
use App\Models\Lead;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class LeadLifecycleService
{
    public function __construct(
        private readonly LeadActivityLogger $activity,
        private readonly AuditLogger $audit,
    ) {}

    public function softDelete(Lead $lead, User $actor, ?string $reason = null): void
    {
        DB::transaction(function () use ($lead, $actor, $reason): void {
            $lead->update([
                'deleted_by' => $actor->id,
                'delete_reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
            ]);

            $lead->delete();

            $this->activity->log($lead, LeadActivityType::Deleted, $actor, [
                'reason' => $reason,
            ]);

            $this->audit->log(
                AuditAction::LeadDeleted,
                $lead,
                $lead->tenant_id,
                ['reason' => $reason],
                $actor,
            );
        });
    }

    public function restore(Lead $lead, User $actor): Lead
    {
        return DB::transaction(function () use ($lead, $actor): Lead {
            $lead->restore();
            $lead->update([
                'deleted_by' => null,
                'delete_reason' => null,
            ]);

            $lead = $lead->fresh();

            $this->activity->log($lead, LeadActivityType::Restored, $actor);
            $this->audit->log(AuditAction::LeadRestored, $lead, $lead->tenant_id, actor: $actor);

            return $lead;
        });
    }
}
