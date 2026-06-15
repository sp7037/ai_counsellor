<?php

namespace App\Models;

use App\Enums\Billing\SubscriptionSource;
use App\Enums\Billing\SubscriptionStatus;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'tenant_id',
    'plan_id',
    'status',
    'source',
    'trial_started_at',
    'trial_ends_at',
    'current_period_started_at',
    'current_period_ends_at',
    'grace_ends_at',
    'cancel_at_period_end',
    'cancelled_at',
    'expired_at',
    'provider_name',
    'provider_customer_id',
    'provider_subscription_id',
    'provider_status',
    'last_webhook_at',
    'created_by',
    'updated_by',
])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Subscription $subscription): void {
            if (empty($subscription->uuid)) {
                $subscription->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'source' => SubscriptionSource::class,
            'trial_started_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'current_period_started_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'cancelled_at' => 'datetime',
            'expired_at' => 'datetime',
            'last_webhook_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SubscriptionEvent::class);
    }

    public function effectiveStatus(?Carbon $at = null): SubscriptionStatus
    {
        $at ??= now();

        if ($this->status === SubscriptionStatus::Trialing
            && $this->trial_ends_at !== null
            && $at->greaterThan($this->trial_ends_at)) {
            return SubscriptionStatus::Expired;
        }

        if ($this->status === SubscriptionStatus::Grace
            && $this->grace_ends_at !== null
            && $at->greaterThan($this->grace_ends_at)) {
            return SubscriptionStatus::PastDue;
        }

        if ($this->status === SubscriptionStatus::Active
            && $this->cancel_at_period_end
            && $this->current_period_ends_at !== null
            && $at->greaterThan($this->current_period_ends_at)) {
            return SubscriptionStatus::Cancelled;
        }

        if (in_array($this->status, [SubscriptionStatus::Active, SubscriptionStatus::PastDue], true)
            && $this->current_period_ends_at !== null
            && $at->greaterThan($this->current_period_ends_at)
            && ! $this->cancel_at_period_end) {
            return SubscriptionStatus::Expired;
        }

        return $this->status;
    }

    public function billingPeriodStart(?Carbon $at = null): Carbon
    {
        $at ??= now();

        if ($this->status === SubscriptionStatus::Trialing && $this->trial_started_at !== null) {
            return $this->trial_started_at->copy()->startOfDay();
        }

        if ($this->current_period_started_at !== null) {
            return $this->current_period_started_at->copy()->startOfDay();
        }

        return $at->copy()->startOfMonth();
    }

    public function billingPeriodEnd(?Carbon $at = null): Carbon
    {
        $at ??= now();

        if ($this->status === SubscriptionStatus::Trialing && $this->trial_ends_at !== null) {
            return $this->trial_ends_at->copy()->endOfDay();
        }

        if ($this->current_period_ends_at !== null) {
            return $this->current_period_ends_at->copy()->endOfDay();
        }

        return $at->copy()->endOfMonth();
    }
}
