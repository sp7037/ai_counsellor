<?php

namespace App\Models;

use App\Enums\Billing\UsageMetric;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'metric',
    'period_start',
    'period_end',
    'used_value',
    'reserved_value',
])]
class TenantUsageCounter extends Model
{
    protected function casts(): array
    {
        return [
            'metric' => UsageMetric::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'used_value' => 'integer',
            'reserved_value' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
