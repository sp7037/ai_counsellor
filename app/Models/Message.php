<?php

namespace App\Models;

use App\Enums\Conversations\MessageRole;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['uuid', 'request_uuid', 'conversation_id', 'role', 'body', 'metadata'])]
class Message extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (Message $message): void {
            if (empty($message->uuid)) {
                $message->uuid = (string) Str::uuid();
            }

            if (empty($message->created_at)) {
                $message->created_at = now();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'role' => MessageRole::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
