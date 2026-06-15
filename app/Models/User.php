<?php

namespace App\Models;

use App\Enums\PlatformRole;
use App\Enums\Tenancy\MembershipStatus;
use App\Enums\Tenancy\TenantRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'platform_role', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (empty($user->uuid)) {
                $user->uuid = (string) Str::uuid();
            }

            if (empty($user->status)) {
                $user->status = UserStatus::Active->value;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'platform_role' => PlatformRole::class,
            'status' => UserStatus::class,
        ];
    }

    public function isPlatformSuperAdmin(): bool
    {
        return $this->platform_role === PlatformRole::SuperAdmin
            && $this->status === UserStatus::Active;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_user')
            ->using(TenantMembership::class)
            ->withPivot(['id', 'role', 'status', 'is_owner', 'joined_at'])
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    public function activeMemberships(): HasMany
    {
        return $this->memberships()
            ->where('status', MembershipStatus::Active->value);
    }

    public function membershipFor(Tenant $tenant): ?TenantMembership
    {
        return $this->memberships()
            ->where('tenant_id', $tenant->id)
            ->first();
    }

    public function hasActiveMembership(Tenant $tenant): bool
    {
        $membership = $this->membershipFor($tenant);

        return $membership !== null && $membership->status->allowsAccess();
    }

    public function tenantRoleFor(Tenant $tenant): ?TenantRole
    {
        return $this->membershipFor($tenant)?->role;
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
