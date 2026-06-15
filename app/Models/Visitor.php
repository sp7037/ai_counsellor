<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['uuid', 'fingerprint_hash', 'first_seen_at', 'last_seen_at'])]
class Visitor extends Model
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        static::creating(function (Visitor $visitor): void {
            if (empty($visitor->uuid)) {
                $visitor->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
