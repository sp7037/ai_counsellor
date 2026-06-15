<?php

namespace App\Models;

use App\Enums\Knowledge\KnowledgeItemStatus;
use App\Enums\Knowledge\KnowledgeItemType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'uuid', 'type', 'status', 'locale', 'title', 'draft_title', 'draft_body',
    'current_version_id', 'service_id', 'course_id', 'institution_id', 'location_id',
    'published_at', 'archived_at', 'created_by', 'updated_by',
])]
class KnowledgeItem extends Model
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        static::creating(function (KnowledgeItem $item): void {
            if (empty($item->uuid)) {
                $item->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'type' => KnowledgeItemType::class,
            'status' => KnowledgeItemStatus::class,
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
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

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(KnowledgeVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(KnowledgeVersion::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
