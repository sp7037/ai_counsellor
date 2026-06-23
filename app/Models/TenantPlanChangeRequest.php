<?php

namespace App\Models;

use App\Enums\Billing\PlanChangeRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TenantPlanChangeRequest extends Model
{
    protected $fillable = [
        'uuid',
        'tenant_id',
        'requested_by',
        'current_plan_id',
        'requested_plan_id',
        'direction',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'admin_note',
    ];

    protected static function booted(): void
    {
        static::creating(function (TenantPlanChangeRequest $request): void {
            if (empty($request->uuid)) {
                $request->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => PlanChangeRequestStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function currentPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'current_plan_id');
    }

    public function requestedPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'requested_plan_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
