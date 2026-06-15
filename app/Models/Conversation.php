<?php

namespace App\Models;

use App\Enums\Conversations\ConversationChannel;
use App\Enums\Conversations\ConversationStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'visitor_id',
    'lead_id',
    'channel',
    'status',
    'source_url',
    'origin_domain',
    'locale',
    'started_at',
    'last_message_at',
    'closed_at',
])]
class Conversation extends Model
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        static::creating(function (Conversation $conversation): void {
            if (empty($conversation->uuid)) {
                $conversation->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'channel' => ConversationChannel::class,
            'status' => ConversationStatus::class,
            'started_at' => 'datetime',
            'last_message_at' => 'datetime',
            'closed_at' => 'datetime',
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

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
