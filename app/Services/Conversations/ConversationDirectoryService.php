<?php

namespace App\Services\Conversations;

use App\Enums\Conversations\ConversationMode;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ConversationDirectoryService
{
    public function __construct(
        private readonly ConversationAccessService $access,
    ) {}

    public function paginateForCounsellor(
        Tenant $tenant,
        User $counsellor,
        array $filters = [],
        int $perPage = 15,
    ): LengthAwarePaginator {
        $query = $this->baseQuery($tenant)
            ->with(['lead:id,public_reference,full_name,service_interest,stage,priority,assigned_to', 'humanOwner:id,name', 'visitor:id,uuid'])
            ->where(function (Builder $builder) use ($counsellor): void {
                $builder->where('human_owner_id', $counsellor->id)
                    ->orWhere(function (Builder $waiting) use ($counsellor): void {
                        $waiting->where('mode', ConversationMode::HandoffRequested->value)
                            ->where(function (Builder $eligible) use ($counsellor): void {
                                $eligible->where('target_counsellor_id', $counsellor->id)
                                    ->orWhereHas('lead', fn (Builder $lead) => $lead->where('assigned_to', $counsellor->id))
                                    ->orWhere(function (Builder $unassigned): void {
                                        $unassigned->whereNull('target_counsellor_id')
                                            ->whereDoesntHave('lead', fn (Builder $lead) => $lead->whereNotNull('assigned_to'));
                                    });
                            });
                    });
            });

        return $this->applyFilters($query, $filters)->paginate($perPage);
    }

    public function paginateForTenantAdmin(
        Tenant $tenant,
        array $filters = [],
        int $perPage = 15,
    ): LengthAwarePaginator {
        $query = $this->baseQuery($tenant)
            ->with(['lead:id,public_reference,full_name,service_interest,stage,priority,assigned_to', 'humanOwner:id,name', 'visitor:id,uuid', 'targetCounsellor:id,name']);

        return $this->applyFilters($query, $filters)->paginate($perPage);
    }

    /**
     * @return array<string, int>
     */
    public function tenantMetrics(Tenant $tenant): array
    {
        $base = Conversation::query()->where('tenant_id', $tenant->id);

        return [
            'active_human' => (clone $base)->where('mode', ConversationMode::Human->value)->count(),
            'waiting_handoffs' => (clone $base)->where('mode', ConversationMode::HandoffRequested->value)->count(),
            'unread_visitor_messages' => (clone $base)->where('counsellor_unread_count', '>', 0)->sum('counsellor_unread_count'),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function counsellorMetrics(Tenant $tenant, User $counsellor): array
    {
        $base = Conversation::query()->where('tenant_id', $tenant->id);

        return [
            'waiting_assigned' => (clone $base)->where('mode', ConversationMode::HandoffRequested->value)
                ->where(function (Builder $q) use ($counsellor): void {
                    $q->where('target_counsellor_id', $counsellor->id)
                        ->orWhereHas('lead', fn (Builder $lead) => $lead->where('assigned_to', $counsellor->id));
                })->count(),
            'active_conversations' => (clone $base)->where('mode', ConversationMode::Human->value)
                ->where('human_owner_id', $counsellor->id)->count(),
            'unread_messages' => (clone $base)->where('human_owner_id', $counsellor->id)
                ->sum('counsellor_unread_count'),
        ];
    }

    private function baseQuery(Tenant $tenant): Builder
    {
        return Conversation::query()
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');
    }

    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['mode'])) {
            $query->where('mode', $filters['mode']);
        }

        if (! empty($filters['search'])) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($filters['search'])).'%';
            $query->where(function (Builder $builder) use ($term): void {
                $builder->where('uuid', 'like', $term)
                    ->orWhereHas('lead', fn (Builder $lead) => $lead
                        ->where('full_name', 'like', $term)
                        ->orWhere('public_reference', 'like', $term))
                    ->orWhereHas('visitor', fn (Builder $visitor) => $visitor
                        ->where('uuid', 'like', $term)
                        ->orWhereRaw('CAST(id AS CHAR) LIKE ?', [$term]));
            });
        }

        if (! empty($filters['counsellor_id'])) {
            $query->where(function (Builder $builder) use ($filters): void {
                $builder->where('human_owner_id', $filters['counsellor_id'])
                    ->orWhere('target_counsellor_id', $filters['counsellor_id']);
            });
        }

        return $query;
    }
}
