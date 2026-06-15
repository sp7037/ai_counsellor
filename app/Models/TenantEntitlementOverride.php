<?php

namespace App\Models;

use App\Enums\Billing\PlanFeature;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'feature',
    'enabled',
    'limit_value',
    'reason',
    'expires_at',
    'created_by',
])]
class TenantEntitlementOverride extends Model
{
    protected function casts(): array
    {
        return [
            'feature' => PlanFeature::class,
            'enabled' => 'boolean',
            'limit_value' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isActive(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
