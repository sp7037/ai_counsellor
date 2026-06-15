<?php

namespace App\Models;

use App\Enums\Messaging\MessagingIntegrationStatus;
use App\Enums\Messaging\MessagingProvider;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'tenant_id',
    'provider',
    'environment',
    'status',
    'phone_number_id',
    'waba_id',
    'display_phone_number',
    'business_display_name',
    'verify_token',
    'access_token',
    'app_secret',
    'is_enabled',
    'last_webhook_at',
    'last_outbound_success_at',
    'last_error_category',
    'configured_by',
])]
class TenantMessagingIntegration extends Model
{
    use BelongsToTenant;

    protected $hidden = [
        'access_token',
        'app_secret',
        'verify_token',
    ];

    protected static function booted(): void
    {
        static::creating(function (TenantMessagingIntegration $integration): void {
            if (empty($integration->uuid)) {
                $integration->uuid = (string) Str::uuid();
            }

            if (empty($integration->verify_token)) {
                $integration->verify_token = Str::random(32);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'provider' => MessagingProvider::class,
            'status' => MessagingIntegrationStatus::class,
            'access_token' => 'array',
            'app_secret' => 'array',
            'is_enabled' => 'boolean',
            'last_webhook_at' => 'datetime',
            'last_outbound_success_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function configuredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'configured_by');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(MessagingContact::class, 'messaging_integration_id');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(MessagingTemplate::class, 'messaging_integration_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'messaging_integration_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MessagingEvent::class, 'messaging_integration_id');
    }

    public function isOperational(): bool
    {
        return $this->is_enabled
            && $this->status === MessagingIntegrationStatus::Connected
            && is_string($this->phone_number_id)
            && $this->phone_number_id !== '';
    }
}
