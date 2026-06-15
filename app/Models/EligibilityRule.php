<?php

namespace App\Models;

use App\Enums\Knowledge\KnowledgePublishableStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'uuid', 'title', 'service_id', 'course_id', 'required_criteria', 'preferred_criteria',
    'priority', 'status', 'published_at', 'created_by',
])]
class EligibilityRule extends Model
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        static::creating(function (EligibilityRule $rule): void {
            if (empty($rule->uuid)) {
                $rule->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => KnowledgePublishableStatus::class,
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

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
