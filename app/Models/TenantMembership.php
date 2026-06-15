<?php

namespace App\Models;

use App\Enums\Tenancy\MembershipStatus;
use App\Enums\Tenancy\TenantRole;
use Database\Factories\TenantMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable([
    'tenant_id',
    'user_id',
    'role',
    'status',
    'is_owner',
    'joined_at',
])]
class TenantMembership extends Pivot
{
    /** @use HasFactory<TenantMembershipFactory> */
    use HasFactory;

    public $incrementing = true;

    protected $table = 'tenant_user';

    protected static function booted(): void
    {
        static::creating(function (TenantMembership $membership): void {
            if (empty($membership->status)) {
                $membership->status = MembershipStatus::Active->value;
            }

            if ($membership->joined_at === null && $membership->status === MembershipStatus::Active->value) {
                $membership->joined_at = now();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'role' => TenantRole::class,
            'status' => MembershipStatus::class,
            'is_owner' => 'boolean',
            'joined_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function allowsAccess(): bool
    {
        return $this->status->allowsAccess();
    }
}
