<?php

namespace App\Models;

use App\Enums\Billing\PlanStatus;
use App\Enums\Billing\TaxTreatment;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'code',
    'name',
    'description',
    'billing_interval',
    'currency',
    'amount_minor',
    'billing_interval_count',
    'tax_treatment',
    'setup_fee_minor',
    'provider_price_id',
    'display_order',
    'is_public',
    'is_purchasable',
    'pricing_effective_from',
    'status',
    'created_by',
    'updated_by',
])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Plan $plan): void {
            if (empty($plan->uuid)) {
                $plan->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => PlanStatus::class,
            'tax_treatment' => TaxTreatment::class,
            'is_public' => 'boolean',
            'is_purchasable' => 'boolean',
            'display_order' => 'integer',
            'amount_minor' => 'integer',
            'billing_interval_count' => 'integer',
            'setup_fee_minor' => 'integer',
            'pricing_effective_from' => 'datetime',
        ];
    }

    public function isPurchasable(): bool
    {
        return $this->is_purchasable
            && $this->status === PlanStatus::Active
            && $this->amount_minor !== null
            && $this->amount_minor > 0
            && is_string($this->currency)
            && strlen($this->currency) === 3;
    }

    public function formattedPrice(): ?string
    {
        if ($this->amount_minor === null || ! $this->currency) {
            return null;
        }

        $major = number_format($this->amount_minor / 100, 2);

        return $this->currency.' '.$major;
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(PlanEntitlement::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
