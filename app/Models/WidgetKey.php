<?php

namespace App\Models;

use App\Enums\Widget\WidgetKeyStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['uuid', 'public_key', 'name', 'status', 'last_rotated_at', 'revoked_at', 'created_by'])]
class WidgetKey extends Model
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        static::creating(function (WidgetKey $key): void {
            if (empty($key->uuid)) {
                $key->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => WidgetKeyStatus::class,
            'last_rotated_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isActive(): bool
    {
        return $this->status === WidgetKeyStatus::Active;
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
