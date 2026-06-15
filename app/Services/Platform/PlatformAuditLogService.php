<?php

namespace App\Services\Platform;

use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PlatformAuditLogService
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = AuditLog::query()
            ->with(['actor:id,name,email,platform_role', 'tenant:id,uuid,name'])
            ->latest('id');

        if (! empty($filters['tenant_id'])) {
            $query->where('tenant_id', (int) $filters['tenant_id']);
        }

        if (! empty($filters['actor_user_id'])) {
            $query->where('actor_user_id', (int) $filters['actor_user_id']);
        }

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['subject_type'])) {
            $query->where('subject_type', $filters['subject_type']);
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
     * @return array<string, mixed>
     */
    public function safeDetail(AuditLog $log): array
    {
        $log->load(['actor:id,name,email,platform_role', 'tenant:id,uuid,name']);

        return [
            'id' => $log->id,
            'created_at' => $log->created_at?->toIso8601String(),
            'action' => $log->action->label(),
            'action_value' => $log->action->value,
            'actor' => $log->actor?->only(['name', 'email']),
            'actor_role' => $log->actor?->platform_role?->value ?? 'tenant',
            'tenant' => $log->tenant?->only(['uuid', 'name']),
            'subject_type' => $log->subject_type ? class_basename($log->subject_type) : null,
            'subject_id' => $log->subject_id,
            'metadata' => $this->redactMetadata($log->metadata ?? []),
            'ip_address' => $log->ip_address,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function redactMetadata(array $metadata): array
    {
        $encoded = json_encode($metadata) ?: '';

        if (preg_match('/sk-[A-Za-z0-9_-]+/i', $encoded)) {
            $encoded = preg_replace('/sk-[A-Za-z0-9_-]+/i', 'sk-[REDACTED]', $encoded) ?? $encoded;
            $metadata = json_decode($encoded, true) ?? $metadata;
        }

        return $metadata;
    }
}
