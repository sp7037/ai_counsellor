<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WidgetSession extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'conversation_id',
        'visitor_id',
        'widget_key_id',
        'origin_domain',
        'token_hash',
        'expires_at',
        'last_used_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (WidgetSession $session): void {
            if (empty($session->uuid)) {
                $session->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    public function widgetKey(): BelongsTo
    {
        return $this->belongsTo(WidgetKey::class);
    }
}
