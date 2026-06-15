<?php

namespace App\Services\Audit;

use App\Enums\Audit\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    public function log(
        AuditAction $action,
        ?Model $subject = null,
        ?int $tenantId = null,
        array $metadata = [],
        ?User $actor = null,
    ): AuditLog {
        $actor ??= Auth::user();

        $sanitizedMetadata = $this->sanitizeMetadata($metadata);

        return AuditLog::query()->create([
            'tenant_id' => $tenantId,
            'actor_user_id' => $actor?->id,
            'action' => $action->value,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'metadata' => $sanitizedMetadata === [] ? null : $sanitizedMetadata,
            'ip_address' => request()->ip(),
        ]);
    }

    private function sanitizeMetadata(array $metadata): array
    {
        $blockedKeys = ['password', 'token', 'secret', 'api_key', 'remember_token'];

        return collect($metadata)
            ->reject(fn ($value, $key) => in_array(strtolower((string) $key), $blockedKeys, true))
            ->toArray();
    }
}
