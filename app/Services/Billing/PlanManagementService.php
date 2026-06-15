<?php

namespace App\Services\Billing;

use App\Enums\Audit\AuditAction;
use App\Enums\Billing\LimitPeriod;
use App\Enums\Billing\PlanFeature;
use App\Enums\Billing\PlanStatus;
use App\Models\Plan;
use App\Models\PlanEntitlement;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlanManagementService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<int, array{feature: string, enabled?: bool, limit_value?: ?int, limit_period?: ?string}>  $features
     */
    public function create(array $data, array $features, User $actor): Plan
    {
        return DB::transaction(function () use ($data, $features, $actor): Plan {
            if (Plan::query()->where('code', $data['code'])->exists()) {
                throw ValidationException::withMessages(['code' => 'Plan code already exists.']);
            }

            $plan = Plan::query()->create([
                'code' => $data['code'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'billing_interval' => $data['billing_interval'] ?? 'monthly',
                'display_order' => $data['display_order'] ?? 0,
                'is_public' => $data['is_public'] ?? true,
                'status' => PlanStatus::Active->value,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->syncFeatures($plan, $features);
            $this->audit->log(AuditAction::PlanCreated, $plan, null, ['code' => $plan->code], $actor);

            return $plan->load('entitlements');
        });
    }

    /**
     * @param  array<int, array{feature: string, enabled?: bool, limit_value?: ?int, limit_period?: ?string}>  $features
     */
    public function update(Plan $plan, array $data, array $features, User $actor): Plan
    {
        return DB::transaction(function () use ($plan, $data, $features, $actor): Plan {
            $plan->update([
                'name' => $data['name'] ?? $plan->name,
                'description' => $data['description'] ?? $plan->description,
                'billing_interval' => $data['billing_interval'] ?? $plan->billing_interval,
                'display_order' => $data['display_order'] ?? $plan->display_order,
                'is_public' => $data['is_public'] ?? $plan->is_public,
                'updated_by' => $actor->id,
            ]);

            $this->syncFeatures($plan, $features);
            $this->audit->log(AuditAction::PlanUpdated, $plan, null, ['code' => $plan->code], $actor);

            return $plan->fresh(['entitlements']);
        });
    }

    public function setStatus(Plan $plan, PlanStatus $status, User $actor): Plan
    {
        $plan->update([
            'status' => $status->value,
            'updated_by' => $actor->id,
        ]);

        $this->audit->log(
            $status === PlanStatus::Active ? AuditAction::PlanActivated : AuditAction::PlanDeactivated,
            $plan,
            null,
            ['code' => $plan->code],
            $actor,
        );

        return $plan;
    }

    /**
     * @param  array<int, array{feature: string, enabled?: bool, limit_value?: ?int, limit_period?: ?string}>  $features
     */
    private function syncFeatures(Plan $plan, array $features): void
    {
        $plan->entitlements()->delete();

        foreach ($features as $row) {
            $feature = PlanFeature::from($row['feature']);

            PlanEntitlement::query()->create([
                'plan_id' => $plan->id,
                'feature' => $feature->value,
                'enabled' => $row['enabled'] ?? true,
                'limit_value' => $row['limit_value'] ?? null,
                'limit_period' => isset($row['limit_period'])
                    ? LimitPeriod::from($row['limit_period'])->value
                    : ($feature->limitMetric()?->periodType()->value),
            ]);
        }
    }
}
