<?php

namespace App\Services\Billing;

use App\Enums\Audit\AuditAction;
use App\Enums\Billing\PlanChangeRequestStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantPlanChangeRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlanChangeRequestService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SubscriptionLifecycleService $subscriptions,
    ) {}

    public function submit(
        Tenant $tenant,
        User $requester,
        Plan $requestedPlan,
        ?string $reason = null,
    ): TenantPlanChangeRequest {
        if (TenantPlanChangeRequest::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', PlanChangeRequestStatus::Pending->value)
            ->exists()) {
            throw ValidationException::withMessages([
                'requested_plan_id' => 'A plan change request is already pending review.',
            ]);
        }

        $subscription = $tenant->subscription()->with('plan')->first();
        $currentPlan = $subscription?->plan;

        if ($currentPlan !== null && $currentPlan->id === $requestedPlan->id) {
            throw ValidationException::withMessages([
                'requested_plan_id' => 'Select a different plan from your current plan.',
            ]);
        }

        $direction = $this->directionFor($currentPlan, $requestedPlan);

        return DB::transaction(function () use ($tenant, $requester, $requestedPlan, $reason, $subscription, $currentPlan, $direction): TenantPlanChangeRequest {
            $request = TenantPlanChangeRequest::query()->create([
                'tenant_id' => $tenant->id,
                'requested_by' => $requester->id,
                'current_plan_id' => $currentPlan?->id,
                'requested_plan_id' => $requestedPlan->id,
                'direction' => $direction,
                'reason' => $reason,
                'status' => PlanChangeRequestStatus::Pending->value,
            ]);

            $this->audit->log(
                AuditAction::PlanChangeRequested,
                $request,
                $tenant->id,
                [
                    'current_plan_id' => $currentPlan?->id,
                    'requested_plan_id' => $requestedPlan->id,
                    'direction' => $direction,
                ],
                $requester,
            );

            return $request;
        });
    }

    public function approve(TenantPlanChangeRequest $request, User $reviewer, ?string $adminNote = null): TenantPlanChangeRequest
    {
        if ($request->status !== PlanChangeRequestStatus::Pending) {
            throw ValidationException::withMessages(['status' => 'Only pending requests can be approved.']);
        }

        return DB::transaction(function () use ($request, $reviewer, $adminNote): TenantPlanChangeRequest {
            $subscription = $request->tenant->subscription()->first();

            if ($subscription === null) {
                $this->subscriptions->assignPlan(
                    $request->tenant,
                    $request->requestedPlan,
                    $reviewer,
                    $adminNote ?: 'Approved plan change request',
                );
            } else {
                $this->subscriptions->changePlan(
                    $subscription,
                    $request->requestedPlan,
                    $reviewer,
                    $adminNote ?: 'Approved plan change request',
                );
            }

            $request->update([
                'status' => PlanChangeRequestStatus::Approved->value,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'admin_note' => $adminNote,
            ]);

            $this->audit->log(
                AuditAction::PlanChangeApproved,
                $request->fresh(),
                $request->tenant_id,
                ['requested_plan_id' => $request->requested_plan_id],
                $reviewer,
            );

            return $request->fresh();
        });
    }

    public function reject(TenantPlanChangeRequest $request, User $reviewer, ?string $adminNote = null): TenantPlanChangeRequest
    {
        if ($request->status !== PlanChangeRequestStatus::Pending) {
            throw ValidationException::withMessages(['status' => 'Only pending requests can be rejected.']);
        }

        $request->update([
            'status' => PlanChangeRequestStatus::Rejected->value,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'admin_note' => $adminNote,
        ]);

        $this->audit->log(
            AuditAction::PlanChangeRejected,
            $request->fresh(),
            $request->tenant_id,
            ['admin_note' => $adminNote],
            $reviewer,
        );

        return $request->fresh();
    }

    private function directionFor(?Plan $current, Plan $requested): string
    {
        if ($current === null) {
            return 'assign';
        }

        if ($requested->display_order > $current->display_order
            || ($requested->display_order === $current->display_order && $requested->amount_minor > $current->amount_minor)) {
            return 'upgrade';
        }

        if ($requested->display_order < $current->display_order
            || ($requested->display_order === $current->display_order && $requested->amount_minor < $current->amount_minor)) {
            return 'downgrade';
        }

        return 'change';
    }
}
