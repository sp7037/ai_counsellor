<?php

namespace App\Models;

use App\Enums\Configuration\CatalogueStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasCatalogueSlug;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['uuid', 'name', 'slug', 'description', 'city', 'state', 'country', 'status', 'sort_order', 'created_by'])]
class Institution extends Model
{
    use BelongsToTenant, HasCatalogueSlug;

    protected static function booted(): void
    {
        static::creating(function (Institution $institution): void {
            if (empty($institution->uuid)) {
                $institution->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => CatalogueStatus::class,
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
