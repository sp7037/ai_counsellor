<?php

namespace App\Models;

use App\Enums\Tenancy\TenantStatus;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'name',
    'slug',
    'legal_name',
    'email',
    'phone',
    'timezone',
    'locale',
    'status',
    'activated_at',
    'suspended_at',
    'suspension_reason',
    'created_by',
])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant): void {
            if (empty($tenant->uuid)) {
                $tenant->uuid = (string) Str::uuid();
            }

            if (empty($tenant->status)) {
                $tenant->status = TenantStatus::Pending->value;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'activated_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function allowsTenantAccess(): bool
    {
        return $this->status->allowsTenantAccess();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_user')
            ->using(TenantMembership::class)
            ->withPivot(['id', 'role', 'status', 'is_owner', 'joined_at'])
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(TenantNote::class);
    }

    public function widgetSettings(): HasOne
    {
        return $this->hasOne(TenantWidgetSettings::class);
    }

    public function widgetKeys(): HasMany
    {
        return $this->hasMany(WidgetKey::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(TenantSettings::class);
    }

    public function officeHours(): HasMany
    {
        return $this->hasMany(TenantOfficeHour::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function institutions(): HasMany
    {
        return $this->hasMany(Institution::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }
}
