<?php

namespace App\Models;

use App\Enums\Leads\LeadPriority;
use App\Enums\Leads\LeadQualificationStatus;
use App\Enums\Leads\LeadSource;
use App\Enums\Leads\LeadStage;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'public_reference',
    'tenant_id',
    'conversation_id',
    'source',
    'source_reference',
    'capture_event_uuid',
    'created_by',
    'full_name',
    'mobile',
    'email',
    'preferred_contact_method',
    'location',
    'state',
    'country',
    'service_interest',
    'programme_interest',
    'enquiry_summary',
    'qualification_notes',
    'lead_score',
    'qualification_status',
    'stage',
    'priority',
    'assigned_to',
    'assigned_at',
    'next_follow_up_at',
    'last_contacted_at',
    'closed_at',
    'lost_reason',
    'invalid_reason',
    'ai_suggested_summary',
    'ai_suggested_score',
    'ai_suggested_priority',
    'score_components',
    'metadata',
])]
class Lead extends Model
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        static::creating(function (Lead $lead): void {
            if (empty($lead->uuid)) {
                $lead->uuid = (string) Str::uuid();
            }

            if (empty($lead->public_reference)) {
                $lead->public_reference = 'LD-'.strtoupper(Str::random(8));
            }
        });
    }

    protected function casts(): array
    {
        return [
            'source' => LeadSource::class,
            'qualification_status' => LeadQualificationStatus::class,
            'stage' => LeadStage::class,
            'priority' => LeadPriority::class,
            'score_components' => 'array',
            'metadata' => 'array',
            'assigned_at' => 'datetime',
            'next_follow_up_at' => 'datetime',
            'last_contacted_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(LeadAssignment::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(LeadNote::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(LeadFollowUp::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
