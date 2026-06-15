<?php

namespace App\Services\Leads;

use App\Enums\Leads\LeadStage;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class LeadTransitionService
{
    /**
     * @var array<string, array<int, LeadStage>>
     */
    private const TRANSITIONS = [
        'new' => [LeadStage::Unassigned, LeadStage::Assigned],
        'unassigned' => [LeadStage::Assigned],
        'assigned' => [LeadStage::ContactAttempted, LeadStage::Contacted, LeadStage::Invalid],
        'contact_attempted' => [LeadStage::Contacted, LeadStage::FollowUpRequired, LeadStage::Invalid, LeadStage::Lost],
        'contacted' => [LeadStage::Qualified, LeadStage::FollowUpRequired, LeadStage::InProgress, LeadStage::Lost, LeadStage::Invalid],
        'qualified' => [LeadStage::FollowUpRequired, LeadStage::InProgress, LeadStage::Converted, LeadStage::Lost],
        'follow_up_required' => [LeadStage::ContactAttempted, LeadStage::Contacted, LeadStage::InProgress, LeadStage::Lost],
        'in_progress' => [LeadStage::Converted, LeadStage::Closed, LeadStage::Lost, LeadStage::FollowUpRequired],
        'converted' => [LeadStage::Closed],
        'closed' => [],
        'lost' => [],
        'invalid' => [],
    ];

    public function canTransition(Lead $lead, LeadStage $target, User $actor, bool $adminOverride = false): bool
    {
        if ($adminOverride && $this->canAdminOverride($actor, $lead)) {
            return true;
        }

        if ($lead->stage->isTerminal() && $target !== $lead->stage) {
            return false;
        }

        $allowed = self::TRANSITIONS[$lead->stage->value] ?? [];

        return in_array($target, $allowed, true);
    }

    public function assertCanTransition(Lead $lead, LeadStage $target, User $actor, bool $adminOverride = false): void
    {
        if (! $this->canTransition($lead, $target, $actor, $adminOverride)) {
            throw ValidationException::withMessages([
                'stage' => 'This stage transition is not permitted.',
            ]);
        }
    }

    public function canAdminOverride(User $actor, Lead $lead): bool
    {
        $role = $actor->tenantRoleFor($lead->tenant);

        return $role?->canManageLeads() ?? $actor->isPlatformSuperAdmin();
    }

    public function canReopen(Lead $lead, User $actor): bool
    {
        if (! $lead->stage->isTerminal()) {
            return false;
        }

        return $this->canAdminOverride($actor, $lead);
    }
}
