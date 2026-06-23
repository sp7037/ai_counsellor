<?php

namespace App\Services\Platform;

use App\Enums\Tenancy\TenantStatus;
use App\Models\Conversation;
use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PlatformTenantDirectoryService
{
    public function __construct(
        private readonly TenantAiStatusPresenter $aiStatus,
    ) {}

    public function paginate(
        ?string $search = null,
        ?string $status = null,
        string $sort = 'created_at',
        string $direction = 'desc',
        int $perPage = 15,
    ): LengthAwarePaginator {
        $query = Tenant::query()
            ->with(['aiConfig.provider'])
            ->withCount('conversations')
            ->withMax('conversations as last_activity_at', 'last_message_at');

        if ($search !== null && trim($search) !== '') {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($search)).'%';
            $query->where(function (Builder $builder) use ($term): void {
                $builder->where('name', 'like', $term)
                    ->orWhere('slug', 'like', $term)
                    ->orWhere('uuid', 'like', $term);
            });
        }

        if ($status === 'all') {
            // No status filter.
        } elseif ($status !== null && $status !== '') {
            $query->where('status', $status);
        } else {
            $query->whereNotIn('status', [
                TenantStatus::Archived->value,
                TenantStatus::Deleted->value,
            ]);
        }

        $allowedSorts = ['created_at', 'name', 'last_activity_at'];
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'created_at';
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction)->paginate($perPage);
    }

    /**
     * @return array<string, mixed>
     */
    public function tenantDetail(Tenant $tenant): array
    {
        $tenant->load(['aiConfig.provider', 'memberships.user', 'suspendedByUser', 'archivedByUser', 'deletedByUser']);
        $tenant->loadCount('conversations');

        $ai = $this->aiStatus->summarize($tenant->aiConfig);

        return [
            'tenant' => $tenant,
            'ai_status' => $ai,
            'credential_mode' => $this->aiStatus->credentialModeLabel($tenant->aiConfig),
            'recent_conversations' => Conversation::query()
                ->where('tenant_id', $tenant->id)
                ->latest('last_message_at')
                ->limit(5)
                ->get(['uuid', 'status', 'last_message_at']),
        ];
    }
}
