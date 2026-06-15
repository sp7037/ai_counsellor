<?php

namespace App\Models;

use App\Enums\Configuration\CatalogueStatus;
use App\Enums\Configuration\StudyMode;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasCatalogueSlug;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['uuid', 'name', 'slug', 'description', 'duration', 'study_mode', 'status', 'sort_order', 'created_by'])]
class Course extends Model
{
    use BelongsToTenant, HasCatalogueSlug;

    protected static function booted(): void
    {
        static::creating(function (Course $course): void {
            if (empty($course->uuid)) {
                $course->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => CatalogueStatus::class,
            'study_mode' => StudyMode::class,
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
