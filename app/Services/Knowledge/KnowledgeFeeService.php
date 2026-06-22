<?php

namespace App\Services\Knowledge;

use App\Enums\Audit\AuditAction;
use App\Enums\Knowledge\KnowledgeFeeType;
use App\Enums\Knowledge\KnowledgePublishableStatus;
use App\Models\KnowledgeFee;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KnowledgeFeeService
{
    public function __construct(
        private readonly KnowledgeContentSanitizer $sanitizer,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function create(Tenant $tenant, array $data, User $actor): KnowledgeFee
    {
        $payload = $this->normalize($data);

        return DB::transaction(function () use ($tenant, $payload, $actor): KnowledgeFee {
            $fee = KnowledgeFee::query()->create([
                ...$payload,
                'tenant_id' => $tenant->id,
                'status' => KnowledgePublishableStatus::Draft->value,
                'created_by' => $actor->id,
            ]);

            $this->auditLogger->log(AuditAction::FeeCreated, $fee, $tenant->id, ['label' => $fee->label], $actor);

            return $fee;
        });
    }

    public function update(KnowledgeFee $fee, array $data, User $actor): KnowledgeFee
    {
        return DB::transaction(function () use ($fee, $data, $actor): KnowledgeFee {
            $fee->update($this->normalize($data, $fee));

            $this->auditLogger->log(AuditAction::FeeUpdated, $fee, $fee->tenant_id, ['label' => $fee->label], $actor);

            return $fee->fresh();
        });
    }

    public function publish(KnowledgeFee $fee, User $actor): KnowledgeFee
    {
        return DB::transaction(function () use ($fee, $actor): KnowledgeFee {
            $fee->update([
                'status' => KnowledgePublishableStatus::Published->value,
                'published_at' => now(),
            ]);

            $this->auditLogger->log(AuditAction::FeeUpdated, $fee, $fee->tenant_id, ['action' => 'published'], $actor);

            return $fee->fresh();
        });
    }

    public function archive(KnowledgeFee $fee, User $actor): KnowledgeFee
    {
        return DB::transaction(function () use ($fee, $actor): KnowledgeFee {
            $fee->update(['status' => KnowledgePublishableStatus::Archived->value]);

            $this->auditLogger->log(AuditAction::FeeArchived, $fee, $fee->tenant_id, [], $actor);

            return $fee->fresh();
        });
    }

    private function normalize(array $data, ?KnowledgeFee $existing = null): array
    {
        $feeType = KnowledgeFeeType::from((string) ($data['fee_type'] ?? $existing?->fee_type?->value ?? KnowledgeFeeType::Exact->value));
        $currency = strtoupper((string) ($data['currency'] ?? $existing?->currency ?? 'INR'));

        if (! in_array($currency, config('knowledge.supported_currencies', ['INR']), true)) {
            throw ValidationException::withMessages(['currency' => 'Unsupported currency.']);
        }

        $amountMinor = (int) ($data['amount_minor'] ?? $existing?->amount_minor ?? 0);
        $amountMaxMinor = isset($data['amount_max_minor']) ? (int) $data['amount_max_minor'] : $existing?->amount_max_minor;

        if ($amountMinor < 0) {
            throw ValidationException::withMessages(['amount_minor' => 'Amount must be zero or greater.']);
        }

        if ($feeType === KnowledgeFeeType::Range) {
            if ($amountMaxMinor === null || $amountMaxMinor < $amountMinor) {
                throw ValidationException::withMessages(['amount_max_minor' => 'Maximum must be greater than minimum.']);
            }
        }

        return [
            'label' => $this->sanitizer->title($data['label'] ?? $existing?->label),
            'fee_type' => $feeType->value,
            'amount_minor' => $amountMinor,
            'amount_max_minor' => $feeType === KnowledgeFeeType::Range ? $amountMaxMinor : null,
            'currency' => $currency,
            'service_id' => $data['service_id'] ?? $existing?->service_id,
            'course_id' => $data['course_id'] ?? $existing?->course_id,
            'institution_id' => $data['institution_id'] ?? $existing?->institution_id,
            'knowledge_item_id' => $data['knowledge_item_id'] ?? $existing?->knowledge_item_id,
            'notes' => $this->sanitizer->optionalText($data['notes'] ?? $existing?->notes, 2000),
            'effective_from' => $data['effective_from'] ?? $existing?->effective_from,
            'effective_until' => $data['effective_until'] ?? $existing?->effective_until,
        ];
    }
}
