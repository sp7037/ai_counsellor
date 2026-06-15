<?php

namespace App\Contracts\Knowledge;

use App\Models\Tenant;

interface KnowledgeRetrievalContract
{
    /**
     * Retrieve published knowledge for a tenant. Module 5 may extend with AI ranking.
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchPublished(Tenant $tenant, string $query, int $limit = 10): array;
}
