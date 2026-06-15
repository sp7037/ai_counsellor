<?php

namespace App\Services\Tenancy;

use App\Enums\Audit\AuditAction;
use App\Enums\Tenancy\MembershipStatus;
use App\Enums\Tenancy\TenantRole;
use App\Enums\Tenancy\TenantStatus;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantLifecycleService
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function createTenant(
        array $attributes,
        ?User $owner = null,
        ?User $actor = null,
    ): Tenant {
        return DB::transaction(function () use ($attributes, $owner, $actor): Tenant {
            $tenant = Tenant::query()->create([
                'name' => $attributes['name'],
                'slug' => $attributes['slug'],
                'legal_name' => $attributes['legal_name'] ?? null,
                'email' => $attributes['email'] ?? null,
                'phone' => $attributes['phone'] ?? null,
                'status' => TenantStatus::Pending->value,
                'created_by' => $actor?->id,
            ]);

            $this->auditLogger->log(
                AuditAction::TenantCreated,
                $tenant,
                $tenant->id,
                ['name' => $tenant->name, 'slug' => $tenant->slug],
                $actor,
            );

            if ($owner !== null) {
                $this->addOwner($tenant, $owner, $actor);
            }

            return $tenant;
        });
    }

    public function activate(Tenant $tenant, ?User $actor = null): Tenant
    {
        if ($tenant->status === TenantStatus::Active) {
            return $tenant;
        }

        $tenant->update([
            'status' => TenantStatus::Active->value,
            'activated_at' => now(),
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);

        $this->auditLogger->log(
            AuditAction::TenantActivated,
            $tenant,
            $tenant->id,
            actor: $actor,
        );

        return $tenant->fresh();
    }

    public function suspend(Tenant $tenant, string $reason, ?User $actor = null): Tenant
    {
        $tenant->update([
            'status' => TenantStatus::Suspended->value,
            'suspended_at' => now(),
            'suspension_reason' => $reason,
        ]);

        $this->auditLogger->log(
            AuditAction::TenantSuspended,
            $tenant,
            $tenant->id,
            ['reason' => $reason],
            $actor,
        );

        return $tenant->fresh();
    }

    public function reactivate(Tenant $tenant, ?User $actor = null): Tenant
    {
        if ($tenant->status === TenantStatus::Cancelled) {
            throw ValidationException::withMessages([
                'status' => 'Cancelled tenants cannot be reactivated in Module 1.',
            ]);
        }

        $tenant->update([
            'status' => TenantStatus::Active->value,
            'activated_at' => $tenant->activated_at ?? now(),
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);

        $this->auditLogger->log(
            AuditAction::TenantReactivated,
            $tenant,
            $tenant->id,
            actor: $actor,
        );

        return $tenant->fresh();
    }

    public function addOwner(Tenant $tenant, User $user, ?User $actor = null): TenantMembership
    {
        return $this->addMember($tenant, $user, TenantRole::Owner, isOwner: true, actor: $actor);
    }

    public function addMember(
        Tenant $tenant,
        User $user,
        TenantRole $role,
        bool $isOwner = false,
        ?User $actor = null,
    ): TenantMembership {
        if (TenantMembership::query()->where('tenant_id', $tenant->id)->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'user_id' => 'This user is already a member of the tenant.',
            ]);
        }

        $membership = TenantMembership::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => $role->value,
            'status' => MembershipStatus::Active->value,
            'is_owner' => $isOwner,
            'joined_at' => now(),
        ]);

        $this->auditLogger->log(
            AuditAction::MembershipCreated,
            $membership,
            $tenant->id,
            ['role' => $role->value, 'user_id' => $user->id],
            $actor,
        );

        return $membership;
    }

    public function createOwnerUser(string $name, string $email, string $password): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);
    }

    public function generateSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $counter = 1;

        while (Tenant::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
