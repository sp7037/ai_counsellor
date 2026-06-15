<?php

namespace App\Services\Leads;

use App\Enums\Leads\FollowUpStatus;
use App\Enums\Leads\LeadStage;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class LeadDirectoryService
{
    public function paginateForTenant(
        Tenant $tenant,
        ?User $counsellor = null,
        array $filters = [],
        int $perPage = 15,
    ): LengthAwarePaginator {
        $query = Lead::query()
            ->with(['assignee:id,name,email'])
            ->where('tenant_id', $tenant->id)
            ->latest('id');

        if ($counsellor !== null) {
            $query->where('assigned_to', $counsellor->id);
        }

        if (! empty($filters['search'])) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($filters['search'])).'%';
            $query->where(function (Builder $builder) use ($term): void {
                $builder->where('full_name', 'like', $term)
                    ->orWhere('mobile', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('public_reference', 'like', $term);
            });
        }

        foreach (['stage', 'qualification_status', 'priority', 'source', 'assigned_to'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (! empty($filters['follow_up_due'])) {
            $query->whereNotNull('next_follow_up_at')
                ->where('next_follow_up_at', '<=', now());
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['to'])->endOfDay());
        }

        return $query->paginate($perPage);
    }

    /**
     * @return array<string, int>
     */
    public function tenantMetrics(Tenant $tenant, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $from ??= Carbon::now()->startOfMonth();
        $to ??= Carbon::now();

        $base = Lead::query()
            ->where('tenant_id', $tenant->id)
            ->whereBetween('created_at', [$from, $to]);

        return [
            'new_leads' => (clone $base)->count(),
            'unassigned' => Lead::query()->where('tenant_id', $tenant->id)->where('stage', LeadStage::Unassigned->value)->count(),
            'assigned' => Lead::query()->where('tenant_id', $tenant->id)->where('stage', LeadStage::Assigned->value)->count(),
            'follow_ups_due' => Lead::query()->where('tenant_id', $tenant->id)->whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<=', now())->count(),
            'qualified' => Lead::query()->where('tenant_id', $tenant->id)->where('stage', LeadStage::Qualified->value)->count(),
            'converted' => Lead::query()->where('tenant_id', $tenant->id)->where('stage', LeadStage::Converted->value)->whereBetween('closed_at', [$from, $to])->count(),
            'lost_invalid' => Lead::query()->where('tenant_id', $tenant->id)->whereIn('stage', [LeadStage::Lost->value, LeadStage::Invalid->value])->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function counsellorMetrics(Tenant $tenant, User $counsellor): array
    {
        $base = Lead::query()->where('tenant_id', $tenant->id)->where('assigned_to', $counsellor->id);

        return [
            'assigned_open' => (clone $base)->whereNotIn('stage', [
                LeadStage::Converted->value,
                LeadStage::Closed->value,
                LeadStage::Lost->value,
                LeadStage::Invalid->value,
            ])->count(),
            'due_today' => LeadFollowUp::query()
                ->where('tenant_id', $tenant->id)
                ->where('assigned_to', $counsellor->id)
                ->where('status', FollowUpStatus::Scheduled->value)
                ->whereDate('due_at', Carbon::today())
                ->count(),
            'overdue' => LeadFollowUp::query()
                ->where('tenant_id', $tenant->id)
                ->where('assigned_to', $counsellor->id)
                ->where('status', FollowUpStatus::Scheduled->value)
                ->where('due_at', '<', now())
                ->count(),
            'in_progress' => (clone $base)->where('stage', LeadStage::InProgress->value)->count(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function counsellorWorkload(Tenant $tenant): array
    {
        return DB::table('tenant_user')
            ->join('users', 'users.id', '=', 'tenant_user.user_id')
            ->leftJoin('leads', function ($join): void {
                $join->on('leads.assigned_to', '=', 'users.id')
                    ->whereNotIn('leads.stage', [
                        LeadStage::Converted->value,
                        LeadStage::Closed->value,
                        LeadStage::Lost->value,
                        LeadStage::Invalid->value,
                    ]);
            })
            ->where('tenant_user.tenant_id', $tenant->id)
            ->where('tenant_user.role', 'staff')
            ->where('tenant_user.status', 'active')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->select([
                'users.id as user_id',
                'users.name',
                'users.email',
                DB::raw('count(leads.id) as open_leads'),
            ])
            ->orderBy('users.name')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }
}
