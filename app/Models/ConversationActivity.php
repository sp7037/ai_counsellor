<?php

namespace App\Models;

use App\Enums\Conversations\ConversationActivityType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationActivity extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'conversation_id',
        'actor_user_id',
        'action_type',
        'metadata',
        'previous_values',
        'new_values',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'action_type' => ConversationActivityType::class,
            'metadata' => 'array',
            'previous_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
