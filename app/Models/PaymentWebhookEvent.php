<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'provider',
    'provider_mode',
    'provider_event_id',
    'event_type',
    'status',
    'event_hash',
    'metadata',
    'processed_at',
])]
class PaymentWebhookEvent extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (PaymentWebhookEvent $event): void {
            if (empty($event->uuid)) {
                $event->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'processed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
