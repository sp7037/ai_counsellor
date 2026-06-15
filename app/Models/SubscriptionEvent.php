<?php

namespace App\Models;

use App\Enums\Billing\SubscriptionEventType;
use App\Enums\Billing\SubscriptionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'subscription_id',
    'tenant_id',
    'event_type',
    'previous_status',
    'new_status',
    'effective_at',
    'actor_user_id',
    'reason',
    'metadata',
])]
class SubscriptionEvent extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (SubscriptionEvent $event): void {
            if (empty($event->uuid)) {
                $event->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'event_type' => SubscriptionEventType::class,
            'previous_status' => SubscriptionStatus::class,
            'new_status' => SubscriptionStatus::class,
            'effective_at' => 'datetime',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
