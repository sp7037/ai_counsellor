<?php

namespace App\Services\Platform;

use App\Models\AiRun;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PlatformAiOperationsService
{
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = AiRun::query()
            ->with(['tenant:id,uuid,name', 'conversation:id,uuid'])
            ->latest('id');

        if (! empty($filters['tenant_id'])) {
            $query->where('tenant_id', (int) $filters['tenant_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['provider'])) {
            $query->where('provider', $filters['provider']);
        }

        if (! empty($filters['credential_source'])) {
            $query->where('credential_source', $filters['credential_source']);
        }

        if (! empty($filters['error_category'])) {
            $query->where('error_category', $filters['error_category']);
        }

        if (! empty($filters['request_uuid'])) {
            $query->where('request_uuid', $filters['request_uuid']);
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
    public function safeRunDetail(AiRun $run): array
    {
        $run->load(['tenant:id,uuid,name', 'conversation:id,uuid', 'message:id,uuid,role']);

        return [
            'id' => $run->id,
            'request_uuid' => $run->request_uuid,
            'tenant' => $run->tenant?->only(['uuid', 'name']),
            'conversation_uuid' => $run->conversation?->uuid,
            'provider' => $run->provider,
            'model' => $run->model,
            'status' => $run->status,
            'credential_source' => $run->credential_source,
            'attempt_number' => $run->attempt_number,
            'input_tokens' => $run->input_tokens,
            'output_tokens' => $run->output_tokens,
            'total_tokens' => $run->total_tokens,
            'latency_ms' => $run->latency_ms,
            'error_category' => $run->error_category,
            'created_at' => $run->created_at?->toIso8601String(),
            'updated_at' => $run->updated_at?->toIso8601String(),
            'assistant_message_uuid' => $run->message?->uuid,
        ];
    }
}
