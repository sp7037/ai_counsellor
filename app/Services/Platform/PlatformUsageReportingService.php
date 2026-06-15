<?php

namespace App\Services\Platform;

use App\Enums\AI\AiRunStatus;
use App\Models\AiRun;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PlatformUsageReportingService
{
    /**
     * @return array<string, int>
     */
    public function periodSummary(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from ??= Carbon::now()->startOfMonth();
        $to ??= Carbon::now();

        $base = AiRun::query()
            ->whereBetween('created_at', [$from, $to]);

        return [
            'total_runs' => (clone $base)->count(),
            'successful_runs' => (clone $base)->where('status', AiRunStatus::Success->value)->count(),
            'failed_runs' => (clone $base)->where('status', AiRunStatus::Failed->value)->count(),
            'input_tokens' => (int) ((clone $base)->where('status', AiRunStatus::Success->value)->sum('input_tokens') ?? 0),
            'output_tokens' => (int) ((clone $base)->where('status', AiRunStatus::Success->value)->sum('output_tokens') ?? 0),
            'total_tokens' => (int) ((clone $base)->where('status', AiRunStatus::Success->value)->sum('total_tokens') ?? 0),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function tenantSummary(int $tenantId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $from ??= Carbon::now()->startOfMonth();
        $to ??= Carbon::now();

        $base = AiRun::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to]);

        return [
            'total_runs' => (clone $base)->count(),
            'successful_runs' => (clone $base)->where('status', AiRunStatus::Success->value)->count(),
            'failed_runs' => (clone $base)->where('status', AiRunStatus::Failed->value)->count(),
            'total_tokens' => (int) ((clone $base)->where('status', AiRunStatus::Success->value)->sum('total_tokens') ?? 0),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function usageByTenant(?Carbon $from = null, ?Carbon $to = null, int $limit = 20): array
    {
        $from ??= Carbon::now()->startOfMonth();
        $to ??= Carbon::now();

        return AiRun::query()
            ->join('tenants', 'tenants.id', '=', 'ai_runs.tenant_id')
            ->whereBetween('ai_runs.created_at', [$from, $to])
            ->groupBy('ai_runs.tenant_id', 'tenants.uuid', 'tenants.name')
            ->select([
                'tenants.uuid as tenant_uuid',
                'tenants.name as tenant_name',
                DB::raw('count(*) as total_runs'),
                DB::raw("sum(case when ai_runs.status = 'success' then 1 else 0 end) as successful_runs"),
                DB::raw("sum(case when ai_runs.status = 'failed' then 1 else 0 end) as failed_runs"),
                DB::raw("sum(case when ai_runs.status = 'success' then coalesce(ai_runs.total_tokens, 0) else 0 end) as total_tokens"),
            ])
            ->orderByDesc('total_tokens')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (array) $row->toArray())
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function usageByProvider(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from ??= Carbon::now()->startOfMonth();
        $to ??= Carbon::now();

        return AiRun::query()
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('provider')
            ->select([
                'provider',
                DB::raw('count(*) as total_runs'),
                DB::raw("sum(case when status = 'success' then coalesce(total_tokens, 0) else 0 end) as total_tokens"),
            ])
            ->orderByDesc('total_runs')
            ->get()
            ->map(fn ($row) => (array) $row->toArray())
            ->all();
    }
}
