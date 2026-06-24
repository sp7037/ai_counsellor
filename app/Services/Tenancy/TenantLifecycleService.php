<?php

namespace App\Services\Tenancy;

use App\Enums\Audit\AuditAction;
use App\Enums\Tenancy\TenantRole;
use App\Enums\Tenancy\TenantStatus;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\EntitlementResolver;
use App\Services\Billing\SubscriptionLifecycleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantLifecycleService
{
    public function __construct(
        private AuditLogger $auditLogger,
        private MembershipLifecycleService $membershipLifecycle,
        private TenantIdentifierReleaseService $identifierRelease,
    ) {}

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
                $tenant = $this->activate($tenant->fresh(), $actor);
                $this->provisionDefaultTrial($tenant, $actor);
            }

            return $tenant;
        });
    }

    public function provisionDefaultTrial(Tenant $tenant, ?User $actor): void
    {
        if ($actor === null || $tenant->subscription()->exists()) {
            return;
        }

        $plan = Plan::query()->where('code', 'trial')->first();

        if ($plan === null) {
            return;
        }

        app(SubscriptionLifecycleService::class)->startTrial(
            $tenant,
            $plan,
            $actor,
            reason: 'Default trial provisioned with new tenant',
        );
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
            'suspended_by' => null,
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
        if (in_array($tenant->status, [TenantStatus::Archived, TenantStatus::Deleted], true)) {
            throw ValidationException::withMessages([
                'status' => 'Archived or deleted tenants cannot be suspended. Restore the tenant first.',
            ]);
        }

        app(EntitlementResolver::class)->clearCache();
        $tenant->update([
            'status' => TenantStatus::Suspended->value,
            'suspended_at' => now(),
            'suspension_reason' => $reason,
            'suspended_by' => $actor?->id,
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
        if (in_array($tenant->status, [TenantStatus::Archived, TenantStatus::Deleted], true)) {
            throw ValidationException::withMessages([
                'status' => 'Archived or deleted tenants must be restored before reactivation.',
            ]);
        }

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
            'suspended_by' => null,
        ]);

        $this->auditLogger->log(
            AuditAction::TenantReactivated,
            $tenant,
            $tenant->id,
            actor: $actor,
        );

        return $tenant->fresh();
    }

    public function archive(Tenant $tenant, string $reason, ?User $actor = null): Tenant
    {
        if (! $tenant->status->canBeArchived()) {
            throw ValidationException::withMessages([
                'status' => 'Tenant must be suspended before it can be archived or deleted.',
            ]);
        }

        app(EntitlementResolver::class)->clearCache();

        $tenant->update([
            'status' => TenantStatus::Archived->value,
            'archived_at' => now(),
            'archived_by' => $actor?->id,
            'archive_reason' => $reason,
        ]);

        $this->auditLogger->log(
            AuditAction::TenantArchived,
            $tenant,
            $tenant->id,
            ['reason' => $reason],
            $actor,
        );

        return $tenant->fresh();
    }

    public function restoreFromArchive(Tenant $tenant, ?User $actor = null): Tenant
    {
        if ($tenant->status !== TenantStatus::Archived) {
            throw ValidationException::withMessages([
                'status' => 'Only archived tenants can be restored.',
            ]);
        }

        app(EntitlementResolver::class)->clearCache();

        $tenant->update([
            'status' => TenantStatus::Suspended->value,
            'archived_at' => null,
            'archived_by' => null,
            'archive_reason' => null,
            'suspended_at' => $tenant->suspended_at ?? now(),
            'suspension_reason' => $tenant->suspension_reason ?? 'Restored from archive.',
        ]);

        $this->auditLogger->log(
            AuditAction::TenantRestored,
            $tenant,
            $tenant->id,
            actor: $actor,
        );

        return $tenant->fresh();
    }

    public function softDelete(Tenant $tenant, string $reason, ?User $actor = null): Tenant
    {
        if (! $tenant->status->canBeDeleted()) {
            throw ValidationException::withMessages([
                'status' => 'Tenant must be suspended and archived before deletion.',
            ]);
        }

        app(EntitlementResolver::class)->clearCache();

        $tenant->update([
            'status' => TenantStatus::Deleted->value,
            'deleted_at' => now(),
            'deleted_by' => $actor?->id,
            'delete_reason' => $reason,
        ]);

        $this->identifierRelease->releaseOnDelete($tenant->fresh(), $actor);

        $this->auditLogger->log(
            AuditAction::TenantDeleted,
            $tenant->fresh(),
            $tenant->id,
            [
                'reason' => $reason,
                'released_slug' => $tenant->fresh()->slug,
                'original_slug' => $tenant->fresh()->original_slug,
            ],
            $actor,
        );

        return $tenant->fresh();
    }

    public function restoreFromDelete(Tenant $tenant, ?User $actor = null): Tenant
    {
        if ($tenant->status !== TenantStatus::Deleted) {
            throw ValidationException::withMessages([
                'status' => 'Only deleted tenants can be restored from deletion.',
            ]);
        }

        app(EntitlementResolver::class)->clearCache();

        $resolved = $this->identifierRelease->resolveIdentifiersForRestore($tenant);

        $tenant->update([
            'status' => TenantStatus::Suspended->value,
            'slug' => $resolved['slug'],
            'email' => $resolved['email'],
            'deleted_at' => null,
            'deleted_by' => null,
            'delete_reason' => null,
            'archived_at' => null,
            'archived_by' => null,
            'archive_reason' => null,
            'original_slug' => $resolved['conflict'] ? $tenant->original_slug : null,
            'original_email' => $resolved['conflict'] ? $tenant->original_email : null,
            'identifier_restore_conflict' => $resolved['conflict'],
            'suspended_at' => $tenant->suspended_at ?? now(),
            'suspension_reason' => $resolved['conflict']
                ? 'Restored from deletion with identifier conflicts. Update slug/email before reactivation.'
                : ($tenant->suspension_reason ?? 'Restored from deletion.'),
        ]);

        $this->auditLogger->log(
            AuditAction::TenantRestored,
            $tenant->fresh(),
            $tenant->id,
            [
                'restored_from' => 'deleted',
                'identifier_conflict' => $resolved['conflict'],
            ],
            $actor,
        );

        return $tenant->fresh();
    }

    /**
     * Soft-delete only. Hard database removal remains blocked.
     *
     * @throws ValidationException
     */
    public function deleteTenant(Tenant $tenant, string $confirmation, string $reason, ?User $actor = null): Tenant
    {
        if ($confirmation !== 'DELETE TENANT') {
            $this->auditLogger->log(
                AuditAction::TenantDeleteAttempted,
                $tenant,
                $tenant->id,
                [
                    'confirmation' => $confirmation,
                    'blocked' => true,
                    'reason' => 'invalid_confirmation',
                ],
                $actor,
            );

            throw ValidationException::withMessages([
                'delete_confirmation' => 'Type DELETE TENANT exactly to confirm.',
            ]);
        }

        if (! $tenant->status->canBeDeleted()) {
            $this->auditLogger->log(
                AuditAction::TenantDeleteAttempted,
                $tenant,
                $tenant->id,
                [
                    'confirmation' => $confirmation,
                    'blocked' => true,
                    'reason' => 'not_archived',
                ],
                $actor,
            );

            throw ValidationException::withMessages([
                'status' => 'Tenant must be suspended before deletion.',
            ]);
        }

        $this->auditLogger->log(
            AuditAction::TenantDeleteAttempted,
            $tenant,
            $tenant->id,
            [
                'confirmation' => $confirmation,
                'blocked' => false,
            ],
            $actor,
        );

        return $this->softDelete($tenant, $reason, $actor);
    }

    /**
     * Hard permanent delete remains unavailable until cascade rules are fully tested.
     *
     * @throws ValidationException
     */
    public function attemptPermanentDelete(Tenant $tenant, string $confirmation, ?User $actor = null): never
    {
        $this->auditLogger->log(
            AuditAction::TenantDeleteAttempted,
            $tenant,
            $tenant->id,
            [
                'confirmation' => $confirmation,
                'blocked' => true,
                'reason' => 'permanent_delete_not_available',
            ],
            $actor,
        );

        throw ValidationException::withMessages([
            'delete' => 'Permanent hard delete is not available. Tenant data is preserved via soft delete.',
        ]);
    }

    public function addOwner(Tenant $tenant, User $user, ?User $actor = null): TenantMembership
    {
        if ($actor?->isPlatformSuperAdmin()) {
            return $this->membershipLifecycle->addMember($tenant, $user, TenantRole::Owner, isOwner: true, actor: $actor);
        }

        throw ValidationException::withMessages([
            'role' => 'Only platform administrators may assign the initial tenant owner.',
        ]);
    }

    public function addMember(
        Tenant $tenant,
        User $user,
        TenantRole $role,
        bool $isOwner = false,
        ?User $actor = null,
    ): TenantMembership {
        return $this->membershipLifecycle->addMember($tenant, $user, $role, $isOwner, $actor);
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
