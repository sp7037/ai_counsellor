<?php

namespace App\Models;

use App\Enums\Conversations\ConversationChannel;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'tenant_id',
    'messaging_integration_id',
    'channel',
    'external_contact_id',
    'display_phone',
    'display_name',
    'provider_contact_id',
    'last_inbound_at',
])]
class MessagingContact extends Model
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        static::creating(function (MessagingContact $contact): void {
            if (empty($contact->uuid)) {
                $contact->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'channel' => ConversationChannel::class,
            'last_inbound_at' => 'datetime',
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

    public function integration(): BelongsTo
    {
        return $this->belongsTo(TenantMessagingIntegration::class, 'messaging_integration_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'messaging_contact_id');
    }
}
