<?php

namespace App\Services\Billing;

use App\Enums\Billing\PlanFeature;
use App\Enums\Billing\UsageMetric;
use App\Models\Conversation;
use App\Models\CounsellorProfile;
use App\Models\KnowledgeItem;
use App\Models\Lead;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantUsageCounter;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class UsageTrackingService
{
    public function getUsed(Tenant $tenant, UsageMetric $metric, Subscription $subscription): int
    {
        $period = $this->periodBounds($subscription, $metric);

        $counter = $this->findCounter($tenant, $metric, $period);

        if ($counter !== null) {
            return (int) $counter->used_value + (int) $counter->reserved_value;
        }

        return $this->calculateUsedFromSource($tenant, $metric, $period['start'], $period['end']);
    }

    /**
     * Atomically reserve usage before a provider call.
     */
    public function reserve(Tenant $tenant, UsageMetric $metric, Subscription $subscription, int $amount = 1, ?int $limit = null): bool
    {
        $period = $this->periodBounds($subscription, $metric);

        return (bool) DB::transaction(function () use ($tenant, $metric, $amount, $period, $limit): bool {
            $counter = $this->lockedCounter($tenant, $metric, $period);

            $used = (int) $counter->used_value + (int) $counter->reserved_value;

            if ($limit !== null && ($used + $amount) > $limit) {
                return false;
            }

            $counter->update([
                'reserved_value' => (int) $counter->reserved_value + $amount,
            ]);

            return true;
        });
    }

    public function confirmReservation(Tenant $tenant, UsageMetric $metric, Subscription $subscription, int $amount = 1): void
    {
        $period = $this->periodBounds($subscription, $metric);

        DB::transaction(function () use ($tenant, $metric, $amount, $period): void {
            $counter = $this->findCounter($tenant, $metric, $period, lock: true);

            if ($counter === null) {
                return;
            }

            $counter->update([
                'reserved_value' => max(0, (int) $counter->reserved_value - $amount),
                'used_value' => (int) $counter->used_value + $amount,
            ]);
        });
    }

    public function releaseReservation(Tenant $tenant, UsageMetric $metric, Subscription $subscription, int $amount = 1): void
    {
        $period = $this->periodBounds($subscription, $metric);

        DB::transaction(function () use ($tenant, $metric, $amount, $period): void {
            $counter = $this->findCounter($tenant, $metric, $period, lock: true);

            if ($counter === null) {
                return;
            }

            $counter->update([
                'reserved_value' => max(0, (int) $counter->reserved_value - $amount),
            ]);
        });
    }

    /**
     * @param  array{start: Carbon, end: Carbon}  $period
     */
    private function lockedCounter(Tenant $tenant, UsageMetric $metric, array $period): TenantUsageCounter
    {
        $counter = $this->findCounter($tenant, $metric, $period, lock: true);

        if ($counter !== null) {
            return $counter;
        }

        $sourceUsed = $this->calculateUsedFromSource($tenant, $metric, $period['start'], $period['end']);

        try {
            return TenantUsageCounter::query()->create([
                'tenant_id' => $tenant->id,
                'metric' => $metric->value,
                'period_start' => $period['start']->toDateString(),
                'period_end' => $period['end']->toDateString(),
                'used_value' => $sourceUsed,
                'reserved_value' => 0,
            ]);
        } catch (UniqueConstraintViolationException) {
            $counter = $this->findCounter($tenant, $metric, $period, lock: true);

            if ($counter === null) {
                throw new \RuntimeException('Failed to resolve usage counter after unique constraint conflict.');
            }

            return $counter;
        }
    }

    /**
     * @param  array{start: Carbon, end: Carbon}  $period
     */
    private function findCounter(Tenant $tenant, UsageMetric $metric, array $period, bool $lock = false): ?TenantUsageCounter
    {
        $query = TenantUsageCounter::query()
            ->where('tenant_id', $tenant->id)
            ->where('metric', $metric->value)
            ->whereDate('period_start', $period['start']->toDateString());

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    public function periodBounds(Subscription $subscription, UsageMetric $metric): array
    {
        if ($metric->periodType()->value === 'total') {
            return [
                'start' => Carbon::parse('2000-01-01'),
                'end' => Carbon::parse('2099-12-31'),
            ];
        }

        return [
            'start' => $subscription->billingPeriodStart(),
            'end' => $subscription->billingPeriodEnd(),
        ];
    }

    public function periodKey(Subscription $subscription, UsageMetric $metric): string
    {
        $bounds = $this->periodBounds($subscription, $metric);

        return $bounds['start']->toDateString().'_'.$bounds['end']->toDateString();
    }

    private function featureForMetric(UsageMetric $metric): PlanFeature
    {
        return match ($metric) {
            UsageMetric::AiRuns, UsageMetric::AiTokens => PlanFeature::AiResponses,
            UsageMetric::KnowledgeItems => PlanFeature::KnowledgeBase,
            UsageMetric::LeadsCreated => PlanFeature::LeadManagement,
            UsageMetric::ActiveCounsellors => PlanFeature::CounsellorWorkspace,
            UsageMetric::ActiveHumanConversations => PlanFeature::HumanHandoff,
        };
    }

    private function calculateUsedFromSource(Tenant $tenant, UsageMetric $metric, Carbon $start, Carbon $end): int
    {
        return match ($metric) {
            UsageMetric::AiRuns => $tenant->aiRuns()
                ->where('status', 'success')
                ->whereBetween('created_at', [$start, $end])
                ->count(),
            UsageMetric::AiTokens => (int) $tenant->aiRuns()
                ->where('status', 'success')
                ->whereBetween('created_at', [$start, $end])
                ->sum(DB::raw('COALESCE(input_tokens, 0) + COALESCE(output_tokens, 0)')),
            UsageMetric::KnowledgeItems => $tenant->id
                ? KnowledgeItem::query()->where('tenant_id', $tenant->id)->count()
                : 0,
            UsageMetric::LeadsCreated => Lead::query()
                ->where('tenant_id', $tenant->id)
                ->whereBetween('created_at', [$start, $end])
                ->count(),
            UsageMetric::ActiveCounsellors => CounsellorProfile::query()
                ->where('tenant_id', $tenant->id)
                ->whereHas('membership', fn ($q) => $q->where('status', 'active'))
                ->count(),
            UsageMetric::ActiveHumanConversations => Conversation::query()
                ->where('tenant_id', $tenant->id)
                ->where('mode', 'human')
                ->count(),
        };
    }
}
