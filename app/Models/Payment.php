<?php

namespace App\Models;

use App\Enums\Billing\PaymentEnvironment;
use App\Enums\Billing\PaymentFailureCategory;
use App\Enums\Billing\PaymentProvider;
use App\Enums\Billing\PaymentStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'tenant_id',
    'payment_order_id',
    'provider',
    'provider_mode',
    'provider_payment_id',
    'amount_minor',
    'currency',
    'status',
    'payment_method_category',
    'verified_at',
    'captured_at',
    'failed_at',
    'refunded_amount_minor',
    'failure_category',
    'metadata',
])]
class Payment extends Model
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            if (empty($payment->uuid)) {
                $payment->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'provider_mode' => PaymentEnvironment::class,
            'status' => PaymentStatus::class,
            'failure_category' => PaymentFailureCategory::class,
            'amount_minor' => 'integer',
            'refunded_amount_minor' => 'integer',
            'verified_at' => 'datetime',
            'captured_at' => 'datetime',
            'failed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function paymentOrder(): BelongsTo
    {
        return $this->belongsTo(PaymentOrder::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
