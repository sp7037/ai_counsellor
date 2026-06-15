<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasCatalogueSlug
{
    public static function bootHasCatalogueSlug(): void
    {
        static::creating(function (Model $model): void {
            if (empty($model->getAttribute('slug')) && ! empty($model->getAttribute('name'))) {
                $model->setAttribute('slug', static::generateUniqueSlug(
                    (string) $model->getAttribute('name'),
                    (int) $model->getAttribute('tenant_id'),
                ));
            }
        });
    }

    public static function generateUniqueSlug(string $name, int $tenantId, ?int $ignoreId = null): string
    {
        $base = Str::slug(Str::limit($name, 120, '')) ?: 'item';
        $slug = $base;
        $counter = 2;

        while (static::query()
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
