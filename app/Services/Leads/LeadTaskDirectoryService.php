<?php

namespace App\Services\Leads;

use App\Enums\Leads\LeadTaskStatus;
use App\Models\LeadTask;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class LeadTaskDirectoryService
{
    /**
     * @return array{today: int, overdue: int, pending: int, completed: int}
     */
    public function counsellorCounts(Tenant $tenant, User $counsellor): array
    {
        $base = LeadTask::query()
            ->where('tenant_id', $tenant->id)
            ->where('assigned_to_user_id', $counsellor->id);

        return [
            'today' => (clone $base)->open()->whereDate('due_at', Carbon::today())->count(),
            'overdue' => (clone $base)->overdue()->count(),
            'pending' => (clone $base)->where('status', LeadTaskStatus::Pending->value)->count(),
            'completed' => (clone $base)->where('status', LeadTaskStatus::Completed->value)
                ->where('completed_at', '>=', Carbon::today()->startOfDay())
                ->count(),
        ];
    }

    /**
     * @return array{today: int, overdue: int, pending: int, completed: int}
     */
    public function tenantCounts(Tenant $tenant): array
    {
        $base = LeadTask::query()->where('tenant_id', $tenant->id);

        return [
            'today' => (clone $base)->open()->whereDate('due_at', Carbon::today())->count(),
            'overdue' => (clone $base)->overdue()->count(),
            'pending' => (clone $base)->whereIn('status', [
                LeadTaskStatus::Pending->value,
                LeadTaskStatus::InProgress->value,
            ])->count(),
            'completed' => (clone $base)->where('status', LeadTaskStatus::Completed->value)
                ->where('completed_at', '>=', Carbon::today()->startOfDay())
                ->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, LeadTask>
     */
    public function listForCounsellor(Tenant $tenant, User $counsellor, array $filters = []): Collection
    {
        return $this->applyFilters(
            LeadTask::query()
                ->with(['lead.assignee'])
                ->where('tenant_id', $tenant->id)
                ->where('assigned_to_user_id', $counsellor->id),
            $filters,
        )->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, LeadTask>
     */
    public function listForTenant(Tenant $tenant, array $filters = []): Collection
    {
        return $this->applyFilters(
            LeadTask::query()
                ->with(['lead.assignee', 'assignee'])
                ->where('tenant_id', $tenant->id),
            $filters,
        )->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->limit(100)
            ->get();
    }

    /**
     * @param  Builder<LeadTask>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<LeadTask>
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['status'])) {
            if ($filters['status'] === 'overdue') {
                $query->overdue();
            } else {
                $query->where('status', $filters['status']);
            }
        }

        if (! empty($filters['assigned_to'])) {
            $query->where('assigned_to_user_id', (int) $filters['assigned_to']);
        }

        if (! empty($filters['due_today'])) {
            $query->open()->whereDate('due_at', Carbon::today());
        }

        if (! empty($filters['due_overdue'])) {
            $query->overdue();
        }

        if (! empty($filters['upcoming'])) {
            $query->open()->where('due_at', '>', now());
        }

        return $query;
    }
}
