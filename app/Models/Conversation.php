<?php

namespace App\Models;

use App\Enums\Conversations\ConversationChannel;
use App\Enums\Conversations\ConversationMode;
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
    'human_owner_id',
    'target_counsellor_id',
    'handoff_request_uuid',
    'channel',
    'status',
    'mode',
    'source_url',
    'origin_domain',
    'locale',
    'started_at',
    'last_message_at',
    'last_visitor_message_at',
    'last_human_message_at',
    'counsellor_unread_count',
    'visitor_last_read_message_id',
    'handoff_requested_at',
    'human_takeover_at',
    'human_released_at',
    'closed_at',
    'close_reason',
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

            if (empty($conversation->mode)) {
                $conversation->mode = ConversationMode::Ai;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'channel' => ConversationChannel::class,
            'status' => ConversationStatus::class,
            'mode' => ConversationMode::class,
            'started_at' => 'datetime',
            'last_message_at' => 'datetime',
            'last_visitor_message_at' => 'datetime',
            'last_human_message_at' => 'datetime',
            'handoff_requested_at' => 'datetime',
            'human_takeover_at' => 'datetime',
            'human_released_at' => 'datetime',
            'closed_at' => 'datetime',
            'counsellor_unread_count' => 'integer',
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

    public function humanOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'human_owner_id');
    }

    public function targetCounsellor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_counsellor_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function handoffs(): HasMany
    {
        return $this->hasMany(ConversationHandoff::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ConversationActivity::class);
    }

    public function readStates(): HasMany
    {
        return $this->hasMany(ConversationReadState::class);
    }

    public function isHumanActive(): bool
    {
        return $this->mode === ConversationMode::Human && $this->human_owner_id !== null;
    }
}
