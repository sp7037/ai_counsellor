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

        if ($actor !== null) {
            $metadata['actor_scope'] = $actor->isPlatformSuperAdmin() ? 'platform' : 'tenant';
        }

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

    public function logMembershipChange(
        AuditAction $action,
        Model $membership,
        int $tenantId,
        array $before,
        array $after,
        ?User $actor = null,
    ): AuditLog {
        return $this->log(
            $action,
            $membership,
            $tenantId,
            [
                'target_user_id' => $before['user_id'] ?? $after['user_id'] ?? null,
                'before' => $before,
                'after' => $after,
            ],
            $actor,
        );
    }

    private function sanitizeMetadata(array $metadata): array
    {
        $blockedKeys = ['password', 'token', 'secret', 'api_key', 'remember_token', 'session_id'];

        return collect($metadata)
            ->reject(fn ($value, $key) => in_array(strtolower((string) $key), $blockedKeys, true))
            ->toArray();
    }
}
