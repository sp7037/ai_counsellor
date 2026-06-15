<?php

namespace App\Services\Billing;

use App\Enums\Audit\AuditAction;
use App\Enums\Billing\SubscriptionEventType;
use App\Enums\Billing\SubscriptionSource;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionLifecycleService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly EntitlementResolver $entitlements,
    ) {}

    public function assignPlan(
        Tenant $tenant,
        Plan $plan,
        User $actor,
        ?string $reason = null,
    ): Subscription {
        return DB::transaction(function () use ($tenant, $plan, $actor, $reason): Subscription {
            $existing = Subscription::query()->where('tenant_id', $tenant->id)->lockForUpdate()->first();

            if ($existing !== null) {
                return $this->changePlan($existing, $plan, $actor, $reason);
            }

            $subscription = Subscription::query()->create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'source' => SubscriptionSource::Manual,
                'current_period_started_at' => now(),
                'current_period_ends_at' => now()->addMonth(),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->recordEvent($subscription, SubscriptionEventType::Created, null, $subscription->status, $actor, $reason);
            $this->recordEvent($subscription, SubscriptionEventType::Activated, null, $subscription->status, $actor, $reason);

            $this->audit->log(AuditAction::SubscriptionAssigned, $subscription, $tenant->id, [
                'plan_code' => $plan->code,
                'status' => $subscription->status->value,
            ], $actor);

            $this->entitlements->clearCache();

            return $subscription->load('plan.entitlements');
        });
    }

    public function startTrial(
        Tenant $tenant,
        Plan $plan,
        User $actor,
        ?int $days = null,
        ?string $reason = null,
    ): Subscription {
        $days ??= (int) config('subscriptions.default_trial_days', 14);

        return DB::transaction(function () use ($tenant, $plan, $actor, $days, $reason): Subscription {
            $existing = Subscription::query()->where('tenant_id', $tenant->id)->lockForUpdate()->first();

            if ($existing !== null) {
                throw ValidationException::withMessages(['subscription' => 'Tenant already has a subscription.']);
            }

            $now = now();

            $subscription = Subscription::query()->create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Trialing,
                'source' => SubscriptionSource::Trial,
                'trial_started_at' => $now,
                'trial_ends_at' => $now->copy()->addDays($days),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->recordEvent($subscription, SubscriptionEventType::Created, null, $subscription->status, $actor, $reason);
            $this->recordEvent($subscription, SubscriptionEventType::TrialStarted, null, $subscription->status, $actor, $reason, [
                'trial_days' => $days,
            ]);

            $this->audit->log(AuditAction::SubscriptionTrialStarted, $subscription, $tenant->id, [
                'plan_code' => $plan->code,
                'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
            ], $actor);

            $this->entitlements->clearCache();

            return $subscription->load('plan.entitlements');
        });
    }

    public function extendTrial(Subscription $subscription, int $days, User $actor, ?string $reason = null): Subscription
    {
        return DB::transaction(function () use ($subscription, $days, $actor, $reason): Subscription {
            $previous = $subscription->status;
            $ends = ($subscription->trial_ends_at ?? now())->copy()->addDays($days);

            $subscription->update([
                'status' => SubscriptionStatus::Trialing,
                'trial_ends_at' => $ends,
                'updated_by' => $actor->id,
            ]);

            $this->recordEvent($subscription, SubscriptionEventType::TrialExtended, $previous, $subscription->status, $actor, $reason, [
                'trial_ends_at' => $ends->toIso8601String(),
            ]);

            $this->audit->log(AuditAction::SubscriptionTrialExtended, $subscription, $subscription->tenant_id, [
                'trial_ends_at' => $ends->toIso8601String(),
            ], $actor);

            $this->entitlements->clearCache();

            return $subscription->fresh(['plan.entitlements']);
        });
    }

    public function activate(Subscription $subscription, User $actor, ?Carbon $periodEnd = null, ?string $reason = null): Subscription
    {
        return DB::transaction(function () use ($subscription, $actor, $periodEnd, $reason): Subscription {
            $previous = $subscription->status;
            $now = now();

            $subscription->update([
                'status' => SubscriptionStatus::Active,
                'source' => SubscriptionSource::Manual,
                'current_period_started_at' => $now,
                'current_period_ends_at' => $periodEnd ?? $now->copy()->addMonth(),
                'trial_started_at' => null,
                'trial_ends_at' => null,
                'grace_ends_at' => null,
                'cancel_at_period_end' => false,
                'cancelled_at' => null,
                'expired_at' => null,
                'updated_by' => $actor->id,
            ]);

            $this->recordEvent($subscription, SubscriptionEventType::Activated, $previous, $subscription->status, $actor, $reason);
            $this->audit->log(AuditAction::SubscriptionActivated, $subscription, $subscription->tenant_id, [], $actor);
            $this->entitlements->clearCache();

            return $subscription->fresh(['plan.entitlements']);
        });
    }

    public function enterGrace(Subscription $subscription, User $actor, ?int $days = null, ?string $reason = null): Subscription
    {
        $days ??= (int) config('subscriptions.default_grace_days', 7);

        return DB::transaction(function () use ($subscription, $actor, $days, $reason): Subscription {
            $previous = $subscription->status;

            $subscription->update([
                'status' => SubscriptionStatus::Grace,
                'grace_ends_at' => now()->addDays($days),
                'updated_by' => $actor->id,
            ]);

            $this->recordEvent($subscription, SubscriptionEventType::GraceStarted, $previous, $subscription->status, $actor, $reason);
            $this->audit->log(AuditAction::SubscriptionGraceApplied, $subscription, $subscription->tenant_id, [
                'grace_ends_at' => $subscription->grace_ends_at?->toIso8601String(),
            ], $actor);
            $this->entitlements->clearCache();

            return $subscription->fresh(['plan.entitlements']);
        });
    }

    public function recoverFromGrace(Subscription $subscription, User $actor, ?string $reason = null): Subscription
    {
        return $this->activate($subscription, $actor, $subscription->current_period_ends_at, $reason);
    }

    public function markPastDue(Subscription $subscription, ?User $actor = null, ?string $reason = null): Subscription
    {
        return DB::transaction(function () use ($subscription, $actor, $reason): Subscription {
            $previous = $subscription->status;

            $subscription->update([
                'status' => SubscriptionStatus::PastDue,
                'updated_by' => $actor?->id,
            ]);

            $this->recordEvent($subscription, SubscriptionEventType::PastDue, $previous, $subscription->status, $actor, $reason);
            $this->audit->log(AuditAction::SubscriptionPastDue, $subscription, $subscription->tenant_id, [], $actor);
            $this->entitlements->clearCache();

            return $subscription->fresh(['plan.entitlements']);
        });
    }

    public function expire(Subscription $subscription, ?User $actor = null, ?string $reason = null): Subscription
    {
        return DB::transaction(function () use ($subscription, $actor, $reason): Subscription {
            $previous = $subscription->status;

            $subscription->update([
                'status' => SubscriptionStatus::Expired,
                'expired_at' => now(),
                'updated_by' => $actor?->id,
            ]);

            $this->recordEvent($subscription, SubscriptionEventType::Expired, $previous, $subscription->status, $actor, $reason ?? 'Subscription expired');
            $this->audit->log(AuditAction::SubscriptionExpired, $subscription, $subscription->tenant_id, [], $actor);
            $this->entitlements->clearCache();

            return $subscription->fresh(['plan.entitlements']);
        });
    }

    public function scheduleCancelAtPeriodEnd(Subscription $subscription, User $actor, ?string $reason = null): Subscription
    {
        return DB::transaction(function () use ($subscription, $actor, $reason): Subscription {
            $subscription->update([
                'cancel_at_period_end' => true,
                'updated_by' => $actor->id,
            ]);

            $this->recordEvent($subscription, SubscriptionEventType::CancelScheduled, $subscription->status, $subscription->status, $actor, $reason);
            $this->audit->log(AuditAction::SubscriptionCancelScheduled, $subscription, $subscription->tenant_id, [], $actor);
            $this->entitlements->clearCache();

            return $subscription->fresh(['plan.entitlements']);
        });
    }

    public function applyPeriodEndCancellation(Subscription $subscription, ?string $reason = null): Subscription
    {
        return DB::transaction(function () use ($subscription, $reason): Subscription {
            $previous = $subscription->status;

            $subscription->update([
                'status' => SubscriptionStatus::Cancelled,
                'cancel_at_period_end' => false,
                'cancelled_at' => now(),
            ]);

            $this->recordEvent($subscription, SubscriptionEventType::Cancelled, $previous, $subscription->status, null, $reason ?? 'Period ended');
            $this->audit->log(AuditAction::SubscriptionCancelled, $subscription, $subscription->tenant_id, ['scheduled' => true]);
            $this->entitlements->clearCache();

            return $subscription->fresh(['plan.entitlements']);
        });
    }

    public function cancelImmediately(Subscription $subscription, User $actor, ?string $reason = null): Subscription
    {
        return DB::transaction(function () use ($subscription, $actor, $reason): Subscription {
            $previous = $subscription->status;

            $subscription->update([
                'status' => SubscriptionStatus::Cancelled,
                'cancel_at_period_end' => false,
                'cancelled_at' => now(),
                'updated_by' => $actor->id,
            ]);

            $this->recordEvent($subscription, SubscriptionEventType::Cancelled, $previous, $subscription->status, $actor, $reason);
            $this->audit->log(AuditAction::SubscriptionCancelled, $subscription, $subscription->tenant_id, [], $actor);
            $this->entitlements->clearCache();

            return $subscription->fresh(['plan.entitlements']);
        });
    }

    public function restore(Subscription $subscription, User $actor, ?string $reason = null): Subscription
    {
        return $this->activate($subscription, $actor, now()->addMonth(), $reason);
    }

    public function changePlan(Subscription $subscription, Plan $plan, User $actor, ?string $reason = null): Subscription
    {
        return DB::transaction(function () use ($subscription, $plan, $actor, $reason): Subscription {
            $previousPlanId = $subscription->plan_id;

            $subscription->update([
                'plan_id' => $plan->id,
                'updated_by' => $actor->id,
            ]);

            $this->recordEvent($subscription, SubscriptionEventType::PlanChanged, $subscription->status, $subscription->status, $actor, $reason, [
                'previous_plan_id' => $previousPlanId,
                'new_plan_id' => $plan->id,
            ]);

            $this->audit->log(AuditAction::SubscriptionPlanChanged, $subscription, $subscription->tenant_id, [
                'plan_code' => $plan->code,
            ], $actor);

            $this->entitlements->clearCache();

            return $subscription->fresh(['plan.entitlements']);
        });
    }

    public function transition(Subscription $subscription, SubscriptionStatus $to, ?User $actor = null, ?string $reason = null): Subscription
    {
        return match ($to) {
            SubscriptionStatus::Active => $this->activate($subscription, $actor ?? throw ValidationException::withMessages(['actor' => 'Actor required.']), reason: $reason),
            SubscriptionStatus::Grace => $this->enterGrace($subscription, $actor ?? throw ValidationException::withMessages(['actor' => 'Actor required.']), reason: $reason),
            SubscriptionStatus::PastDue => $this->markPastDue($subscription, $actor, $reason),
            SubscriptionStatus::Expired => $this->expire($subscription, $actor, $reason),
            SubscriptionStatus::Cancelled => $this->cancelImmediately($subscription, $actor ?? throw ValidationException::withMessages(['actor' => 'Actor required.']), $reason),
            default => throw ValidationException::withMessages(['status' => 'Transition not supported directly.']),
        };
    }

    public function recordEvent(
        Subscription $subscription,
        SubscriptionEventType $type,
        ?SubscriptionStatus $previous,
        ?SubscriptionStatus $new,
        ?User $actor,
        ?string $reason = null,
        array $metadata = [],
    ): SubscriptionEvent {
        return SubscriptionEvent::query()->create([
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'event_type' => $type->value,
            'previous_status' => $previous?->value,
            'new_status' => $new?->value,
            'effective_at' => now(),
            'actor_user_id' => $actor?->id,
            'reason' => $reason,
            'metadata' => $metadata === [] ? null : $metadata,
            'created_at' => now(),
        ]);
    }
}
