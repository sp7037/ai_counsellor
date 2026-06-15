<?php

namespace App\Models;

use App\Enums\Billing\PaymentEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'tenant_id',
    'payment_order_id',
    'payment_id',
    'event_type',
    'source',
    'metadata',
])]
class PaymentEvent extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (PaymentEvent $event): void {
            if (empty($event->uuid)) {
                $event->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'event_type' => PaymentEventType::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function paymentOrder(): BelongsTo
    {
        return $this->belongsTo(PaymentOrder::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
