<?php

namespace App\Models;

use App\Enums\Billing\LimitPeriod;
use App\Enums\Billing\PlanFeature;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'plan_id',
    'feature',
    'enabled',
    'limit_value',
    'limit_period',
])]
class PlanEntitlement extends Model
{
    protected $table = 'plan_features';

    protected function casts(): array
    {
        return [
            'feature' => PlanFeature::class,
            'enabled' => 'boolean',
            'limit_value' => 'integer',
            'limit_period' => LimitPeriod::class,
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isUnlimited(): bool
    {
        return $this->limit_value === null;
    }
}
