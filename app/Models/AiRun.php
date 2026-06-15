<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'request_uuid',
    'conversation_id',
    'triggering_message_id',
    'message_id',
    'provider',
    'model',
    'credential_source',
    'input_tokens',
    'output_tokens',
    'total_tokens',
    'latency_ms',
    'status',
    'error_category',
    'attempt_number',
])]
class AiRun extends Model
{
    use BelongsToTenant;

    protected $hidden = [
        'request_uuid',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
