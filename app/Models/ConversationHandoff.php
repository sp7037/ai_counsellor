<?php

namespace App\Models;

use App\Enums\Conversations\HandoffRecordStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationHandoff extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'conversation_id',
        'counsellor_id',
        'assigned_by',
        'status',
        'is_current',
        'note',
        'claimed_at',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => HandoffRecordStatus::class,
            'is_current' => 'boolean',
            'claimed_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function counsellor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counsellor_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
