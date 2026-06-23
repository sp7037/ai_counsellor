<?php

namespace App\Services\Tenancy;

use App\Enums\Audit\AuditAction;
use App\Enums\Tenancy\TenantStatus;
use App\Enums\UserStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogger;

class TenantIdentifierReleaseService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function releaseOnDelete(Tenant $tenant, ?User $actor = null): void
    {
        $updates = [];

        if ($tenant->original_slug === null && ! str_ends_with($tenant->slug, '-deleted-'.$tenant->id)) {
            $updates['original_slug'] = $tenant->slug;
            $updates['slug'] = $this->releasedSlug($tenant);
        }

        if ($tenant->email !== null && ! str_ends_with((string) $tenant->email, '@deleted.local')) {
            $updates['original_email'] = $tenant->email;
            $updates['email'] = $this->releasedTenantEmail($tenant);
        }

        if ($updates !== []) {
            $tenant->update($updates);
        }

        foreach ($tenant->memberships()->where('is_owner', true)->with('user')->get() as $membership) {
            $this->releaseOwnerEmailIfSafe($membership->user, $tenant, $actor);
        }
    }

    /**
     * @return array{slug: string, email: ?string, conflict: bool}
     */
    public function resolveIdentifiersForRestore(Tenant $tenant): array
    {
        $conflict = false;
        $slug = $tenant->slug;
        $email = $tenant->email;

        if ($tenant->original_slug !== null && ! $this->slugInUseByOtherTenant($tenant->original_slug, $tenant->id)) {
            $slug = $tenant->original_slug;
        } elseif ($tenant->original_slug !== null) {
            $conflict = true;
        }

        if ($tenant->original_email !== null && ! $this->tenantEmailInUseByOther($tenant->original_email, $tenant->id)) {
            $email = $tenant->original_email;
        } elseif ($tenant->original_email !== null) {
            $conflict = true;
        }

        return compact('slug', 'email', 'conflict');
    }

    public function slugInUseByOtherTenant(string $slug, ?int $exceptTenantId = null): bool
    {
        $query = Tenant::query()->where('slug', $slug);

        if ($exceptTenantId !== null) {
            $query->where('id', '!=', $exceptTenantId);
        }

        return $query->exists();
    }

    public function tenantEmailInUseByOther(?string $email, ?int $exceptTenantId = null): bool
    {
        if ($email === null || trim($email) === '') {
            return false;
        }

        $query = Tenant::query()->where('email', $email);

        if ($exceptTenantId !== null) {
            $query->where('id', '!=', $exceptTenantId);
        }

        return $query->exists();
    }

    public function releasedSlug(Tenant $tenant): string
    {
        $base = $tenant->original_slug ?? $tenant->slug;
        $suffix = '-deleted-'.$tenant->id;
        $maxBaseLength = max(1, 255 - strlen($suffix));

        return rtrim(substr($base, 0, $maxBaseLength), '-').$suffix;
    }

    public function releasedTenantEmail(Tenant $tenant): string
    {
        return 'deleted+'.$tenant->uuid.'@deleted.local';
    }

    public function releasedUserEmail(User $user): string
    {
        return 'deleted+'.$user->uuid.'@deleted.local';
    }

    public function isReleasedEmail(string $email): bool
    {
        return str_ends_with(strtolower($email), '@deleted.local');
    }

    public function releaseOwnerEmailIfSafe(User $user, Tenant $tenant, ?User $actor = null): bool
    {
        if ($user->platform_role !== null || $user->isPlatformSuperAdmin()) {
            return false;
        }

        if ($this->isReleasedEmail($user->email)) {
            return false;
        }

        $belongsToOtherLiveTenants = $user->memberships()
            ->where('tenant_id', '!=', $tenant->id)
            ->whereHas('tenant', fn ($query) => $query->where('status', '!=', TenantStatus::Deleted->value))
            ->exists();

        if ($belongsToOtherLiveTenants) {
            return false;
        }

        $originalEmail = $user->email;

        $user->update([
            'original_email' => $originalEmail,
            'email' => $this->releasedUserEmail($user),
            'status' => UserStatus::Disabled->value,
        ]);

        $this->auditLogger->log(
            AuditAction::UserEmailReleased,
            $user,
            $tenant->id,
            [
                'original_email' => $originalEmail,
                'released_email' => $user->email,
                'tenant_id' => $tenant->id,
            ],
            $actor,
        );

        return true;
    }
}
