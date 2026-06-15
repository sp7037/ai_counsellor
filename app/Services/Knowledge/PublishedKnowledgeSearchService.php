<?php

namespace App\Services\Knowledge;

use App\Contracts\Knowledge\KnowledgeRetrievalContract;
use App\Enums\Knowledge\KnowledgeItemStatus;
use App\Models\KnowledgeItem;
use App\Models\Tenant;

class PublishedKnowledgeSearchService implements KnowledgeRetrievalContract
{
    public function searchPublished(Tenant $tenant, string $query, int $limit = 10): array
    {
        $query = trim($query);
        $maxQuery = config('knowledge.max_search_query_length', 120);
        $query = mb_substr($query, 0, $maxQuery);

        if ($query === '') {
            return [];
        }

        $limit = min($limit, config('knowledge.max_search_results', 20));
        $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $query);
        $like = '%'.$escaped.'%';

        return KnowledgeItem::query()
            ->where('knowledge_items.tenant_id', $tenant->id)
            ->where('status', KnowledgeItemStatus::Published->value)
            ->whereNotNull('current_version_id')
            ->join('knowledge_versions', 'knowledge_items.current_version_id', '=', 'knowledge_versions.id')
            ->where(function ($builder) use ($like): void {
                $builder->where('knowledge_versions.title', 'like', $like)
                    ->orWhere('knowledge_versions.body', 'like', $like);
            })
            ->orderByDesc('knowledge_items.published_at')
            ->limit($limit)
            ->get([
                'knowledge_items.uuid',
                'knowledge_items.type',
                'knowledge_items.locale',
                'knowledge_versions.title',
                'knowledge_versions.body',
                'knowledge_versions.version_number',
            ])
            ->map(fn ($row) => [
                'uuid' => $row->uuid,
                'type' => $row->type,
                'locale' => $row->locale,
                'title' => $row->title,
                'excerpt' => mb_substr($row->body, 0, 280),
                'version_number' => $row->version_number,
            ])
            ->all();
    }
}
