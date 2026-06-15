<?php

namespace App\Services\Platform;

use App\Enums\AI\AiCredentialMode;
use App\Enums\AI\AiRunStatus;
use App\Enums\Tenancy\TenantStatus;
use App\Models\AiRun;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\TenantAiConfig;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PlatformDashboardService
{
    public function __construct(
        private readonly TenantAiStatusPresenter $aiStatus,
    ) {}

    /**
     * @return array<string, int|float|null>
     */
    public function summaryCards(): array
    {
        $today = Carbon::today();

        return [
            'total_tenants' => Tenant::query()->count(),
            'active_tenants' => Tenant::query()->where('status', TenantStatus::Active->value)->count(),
            'suspended_tenants' => Tenant::query()->where('status', TenantStatus::Suspended->value)->count(),
            'total_conversations' => Conversation::query()->count(),
            'ai_runs_today' => AiRun::query()->whereDate('created_at', $today)->count(),
            'ai_success_today' => AiRun::query()->whereDate('created_at', $today)->where('status', AiRunStatus::Success->value)->count(),
            'ai_failed_today' => AiRun::query()->whereDate('created_at', $today)->where('status', AiRunStatus::Failed->value)->count(),
            'tokens_period' => (int) (AiRun::query()
                ->where('created_at', '>=', Carbon::now()->startOfMonth())
                ->where('status', AiRunStatus::Success->value)
                ->sum('total_tokens') ?? 0),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentTenants(int $limit = 8): array
    {
        return Tenant::query()
            ->with(['aiConfig.provider'])
            ->withCount('conversations')
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(function (Tenant $tenant): array {
                $ai = $this->aiStatus->summarize($tenant->aiConfig);

                return [
                    'uuid' => $tenant->uuid,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'status' => $tenant->status->label(),
                    'status_value' => $tenant->status->value,
                    'ai_status' => $ai['label'],
                    'ai_variant' => $ai['variant'],
                    'conversations_count' => $tenant->conversations_count,
                    'updated_at' => $tenant->updated_at?->diffForHumans(),
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function aiOperationsOverview(): array
    {
        $today = Carbon::today();

        $statusCounts = AiRun::query()
            ->whereDate('created_at', $today)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $failureCategories = AiRun::query()
            ->whereDate('created_at', $today)
            ->where('status', AiRunStatus::Failed->value)
            ->whereNotNull('error_category')
            ->select('error_category', DB::raw('count(*) as total'))
            ->groupBy('error_category')
            ->orderByDesc('total')
            ->limit(8)
            ->pluck('total', 'error_category');

        $credentialSources = AiRun::query()
            ->whereDate('created_at', $today)
            ->whereNotNull('credential_source')
            ->select('credential_source', DB::raw('count(*) as total'))
            ->groupBy('credential_source')
            ->pluck('total', 'credential_source');

        $recentFailures = AiRun::query()
            ->with('tenant:id,uuid,name')
            ->where('status', AiRunStatus::Failed->value)
            ->latest('id')
            ->limit(6)
            ->get()
            ->map(fn (AiRun $run) => [
                'id' => $run->id,
                'request_uuid' => $run->request_uuid,
                'tenant' => $run->tenant?->name,
                'tenant_uuid' => $run->tenant?->uuid,
                'provider' => $run->provider,
                'error_category' => $run->error_category,
                'created_at' => $run->created_at?->diffForHumans(),
            ])
            ->all();

        return [
            'processing' => (int) ($statusCounts[AiRunStatus::Processing->value] ?? 0),
            'success' => (int) ($statusCounts[AiRunStatus::Success->value] ?? 0),
            'failed' => (int) ($statusCounts[AiRunStatus::Failed->value] ?? 0),
            'failure_categories' => $failureCategories->all(),
            'credential_sources' => $credentialSources->all(),
            'recent_failures' => $recentFailures,
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function platformAlerts(): array
    {
        $alerts = [];

        $missingKeys = TenantAiConfig::query()
            ->where('enabled', true)
            ->where('credential_mode', AiCredentialMode::TenantKeyRequired->value)
            ->whereNull('encrypted_api_key')
            ->count();

        if ($missingKeys > 0) {
            $alerts[] = [
                'level' => 'warning',
                'message' => "{$missingKeys} tenant(s) require a tenant API key but none is configured.",
            ];
        }

        $suspended = Tenant::query()->where('status', TenantStatus::Suspended->value)->count();
        if ($suspended > 0) {
            $alerts[] = [
                'level' => 'info',
                'message' => "{$suspended} tenant(s) are currently suspended.",
            ];
        }

        $recentFailures = AiRun::query()
            ->where('status', AiRunStatus::Failed->value)
            ->where('created_at', '>=', Carbon::now()->subHours(24))
            ->count();

        if ($recentFailures >= 5) {
            $alerts[] = [
                'level' => 'warning',
                'message' => "{$recentFailures} AI failures recorded in the last 24 hours.",
            ];
        }

        return $alerts;
    }
}
