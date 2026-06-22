<?php

namespace App\Models;

use App\Enums\Leads\LeadTaskPriority;
use App\Enums\Leads\LeadTaskStatus;
use App\Enums\Leads\LeadTaskType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'lead_id',
    'assigned_to_user_id',
    'created_by_user_id',
    'title',
    'description',
    'task_type',
    'priority',
    'status',
    'due_at',
    'completed_at',
    'metadata',
])]
class LeadTask extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'task_type' => LeadTaskType::class,
            'priority' => LeadTaskPriority::class,
            'status' => LeadTaskStatus::class,
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isOverdue(): bool
    {
        if (! $this->status->isOpen() || $this->due_at === null) {
            return false;
        }

        return $this->due_at->isPast();
    }

    public function displayStatus(): LeadTaskStatus
    {
        if ($this->isOverdue()) {
            return LeadTaskStatus::Overdue;
        }

        return $this->status;
    }

    /**
     * @param  Builder<LeadTask>  $query
     * @return Builder<LeadTask>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            LeadTaskStatus::Pending->value,
            LeadTaskStatus::InProgress->value,
        ]);
    }

    /**
     * @param  Builder<LeadTask>  $query
     * @return Builder<LeadTask>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()
            ->whereNotNull('due_at')
            ->where('due_at', '<', now());
    }
}
