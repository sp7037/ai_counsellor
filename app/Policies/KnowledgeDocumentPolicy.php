<?php

namespace App\Policies;

use App\Models\KnowledgeDocument;
use App\Models\Tenant;
use App\Models\User;

class KnowledgeDocumentPolicy
{
    public function viewAny(User $user, Tenant $tenant): bool
    {
        return app(TenantKnowledgePolicy::class)->viewAny($user, $tenant);
    }

    public function upload(User $user, Tenant $tenant): bool
    {
        return app(TenantKnowledgePolicy::class)->manage($user, $tenant);
    }

    public function download(User $user, KnowledgeDocument $document): bool
    {
        return app(TenantKnowledgePolicy::class)->viewAny($user, $document->tenant);
    }

    public function delete(User $user, KnowledgeDocument $document): bool
    {
        return app(TenantKnowledgePolicy::class)->manage($user, $document->tenant);
    }
}
