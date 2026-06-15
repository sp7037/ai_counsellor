<?php

namespace App\Services\Tenancy;

use App\Enums\Audit\AuditAction;
use App\Enums\Tenancy\MembershipStatus;
use App\Enums\Tenancy\TenantRole;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MembershipLifecycleService
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function addMember(
        Tenant $tenant,
        User $user,
        TenantRole $role,
        bool $isOwner = false,
        ?User $actor = null,
    ): TenantMembership {
        Gate::authorize('create', [TenantMembership::class, $tenant]);

        $this->assertRoleAssignableByActor($tenant, $role, $actor);
        $this->assertCannotAlterPlatformRole($user);

        if (TenantMembership::query()->where('tenant_id', $tenant->id)->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'user_id' => 'This user is already a member of the tenant.',
            ]);
        }

        return DB::transaction(function () use ($tenant, $user, $role, $isOwner, $actor): TenantMembership {
            $membership = TenantMembership::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'role' => $role->value,
                'status' => MembershipStatus::Active->value,
                'is_owner' => $isOwner,
                'joined_at' => now(),
            ]);

            $this->auditLogger->logMembershipChange(
                AuditAction::MembershipCreated,
                $membership,
                $tenant->id,
                [],
                $this->membershipSnapshot($membership),
                $actor,
            );

            return $membership;
        });
    }

    public function changeRole(
        TenantMembership $membership,
        TenantRole $newRole,
        ?User $actor = null,
    ): TenantMembership {
        Gate::authorize('updateRole', $membership);

        $this->assertRoleAssignableByActor($membership->tenant, $newRole, $actor);
        $this->assertCannotAlterPlatformRole($membership->user);

        if ($membership->role === $newRole) {
            return $membership;
        }

        if ($this->isActiveOwner($membership) && $newRole !== TenantRole::Owner) {
            $this->assertNotFinalActiveOwner($membership->tenant, $membership);
        }

        return DB::transaction(function () use ($membership, $newRole, $actor): TenantMembership {
            $before = $this->membershipSnapshot($membership);

            $membership->update([
                'role' => $newRole->value,
                'is_owner' => $newRole === TenantRole::Owner ? $membership->is_owner : false,
            ]);

            $membership->refresh();

            $this->auditLogger->logMembershipChange(
                AuditAction::MembershipRoleChanged,
                $membership,
                $membership->tenant_id,
                $before,
                $this->membershipSnapshot($membership),
                $actor,
            );

            return $membership;
        });
    }

    public function changeStatus(
        TenantMembership $membership,
        MembershipStatus $newStatus,
        ?User $actor = null,
    ): TenantMembership {
        Gate::authorize('updateStatus', $membership);

        $this->assertCannotAlterPlatformRole($membership->user);

        if ($membership->status === $newStatus) {
            return $membership;
        }

        if ($this->isActiveOwner($membership) && ! $newStatus->allowsAccess()) {
            $this->assertNotFinalActiveOwner($membership->tenant, $membership);
        }

        return DB::transaction(function () use ($membership, $newStatus, $actor): TenantMembership {
            $before = $this->membershipSnapshot($membership);

            $membership->update(['status' => $newStatus->value]);

            $membership->refresh();

            $this->auditLogger->logMembershipChange(
                AuditAction::MembershipStatusChanged,
                $membership,
                $membership->tenant_id,
                $before,
                $this->membershipSnapshot($membership),
                $actor,
            );

            return $membership;
        });
    }

    public function removeMember(TenantMembership $membership, ?User $actor = null): void
    {
        Gate::authorize('delete', $membership);

        $this->assertCannotAlterPlatformRole($membership->user);

        if ($this->isActiveOwner($membership)) {
            $this->assertNotFinalActiveOwner($membership->tenant, $membership);
        }

        DB::transaction(function () use ($membership, $actor): void {
            $before = $this->membershipSnapshot($membership);
            $tenantId = $membership->tenant_id;

            $membership->delete();

            $this->auditLogger->logMembershipChange(
                AuditAction::MembershipRemoved,
                $membership,
                $tenantId,
                $before,
                [],
                $actor,
            );
        });
    }

    private function assertRoleAssignableByActor(Tenant $tenant, TenantRole $role, ?User $actor): void
    {
        if ($actor?->isPlatformSuperAdmin()) {
            return;
        }

        if ($role === TenantRole::Owner) {
            throw ValidationException::withMessages([
                'role' => 'Only platform administrators may assign the owner role.',
            ]);
        }

        $actorRole = $actor?->tenantRoleFor($tenant);

        if ($actorRole === null || ! $actorRole->canAssignRole($role)) {
            throw ValidationException::withMessages([
                'role' => 'You are not authorized to assign this role.',
            ]);
        }
    }

    private function assertCannotAlterPlatformRole(User $user): void
    {
        if ($user->isPlatformSuperAdmin()) {
            throw ValidationException::withMessages([
                'user_id' => 'Platform administrator accounts cannot be managed through tenant membership.',
            ]);
        }
    }

    private function assertNotFinalActiveOwner(Tenant $tenant, TenantMembership $membership): void
    {
        $activeOwners = TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', MembershipStatus::Active->value)
            ->where(function ($query): void {
                $query->where('is_owner', true)
                    ->orWhere('role', TenantRole::Owner->value);
            })
            ->count();

        if ($activeOwners <= 1 && $this->isActiveOwner($membership)) {
            throw ValidationException::withMessages([
                'membership' => 'The final active tenant owner cannot be removed, deactivated, or demoted without transferring ownership first.',
            ]);
        }
    }

    private function isActiveOwner(TenantMembership $membership): bool
    {
        return $membership->status === MembershipStatus::Active
            && ($membership->is_owner || $membership->role === TenantRole::Owner);
    }

    private function membershipSnapshot(TenantMembership $membership): array
    {
        return [
            'membership_id' => $membership->id,
            'user_id' => $membership->user_id,
            'role' => $membership->role->value,
            'status' => $membership->status->value,
            'is_owner' => $membership->is_owner,
        ];
    }
}
