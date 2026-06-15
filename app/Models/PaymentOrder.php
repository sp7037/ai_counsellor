<?php

namespace App\Models;

use App\Enums\Billing\PaymentEnvironment;
use App\Enums\Billing\PaymentOrderStatus;
use App\Enums\Billing\PaymentProvider;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'tenant_id',
    'plan_id',
    'subscription_id',
    'checkout_request_uuid',
    'provider',
    'provider_mode',
    'provider_order_id',
    'internal_reference',
    'amount_minor',
    'currency',
    'status',
    'description',
    'receipt_reference',
    'initiated_by',
    'expires_at',
    'paid_at',
    'failed_at',
    'cancelled_at',
    'subscription_activation_completed_at',
    'activated_subscription_id',
    'notification_key',
    'metadata',
])]
class PaymentOrder extends Model
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        static::creating(function (PaymentOrder $order): void {
            if (empty($order->uuid)) {
                $order->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'provider_mode' => PaymentEnvironment::class,
            'status' => PaymentOrderStatus::class,
            'amount_minor' => 'integer',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'subscription_activation_completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function activatedSubscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'activated_subscription_id');
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }

    public function successfulPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->where('status', 'captured');
    }

    public function isPaid(): bool
    {
        return $this->status === PaymentOrderStatus::Paid;
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
