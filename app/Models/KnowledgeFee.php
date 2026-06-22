<?php

namespace App\Models;

use App\Enums\Knowledge\KnowledgeFeeType;
use App\Enums\Knowledge\KnowledgePublishableStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'tenant_id', 'uuid', 'label', 'fee_type', 'amount_minor', 'amount_max_minor', 'currency',
    'service_id', 'course_id', 'institution_id', 'knowledge_item_id',
    'notes', 'effective_from', 'effective_until', 'status', 'published_at', 'created_by',
])]
class KnowledgeFee extends Model
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        static::creating(function (KnowledgeFee $fee): void {
            if (empty($fee->uuid)) {
                $fee->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'fee_type' => KnowledgeFeeType::class,
            'status' => KnowledgePublishableStatus::class,
            'effective_from' => 'date',
            'effective_until' => 'date',
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
