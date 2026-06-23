<?php

namespace App\Services\Platform;

use App\Enums\Tenancy\TenantStatus;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Tenancy\TenantIdentifierReleaseService;

class PlatformUserLookupService
{
    public function __construct(
        private readonly TenantIdentifierReleaseService $identifiers,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function lookup(string $email): array
    {
        $normalized = strtolower(trim($email));

        if ($normalized === '' || ! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            return [
                'valid_email' => false,
                'exists' => false,
                'message' => 'Enter a valid email address.',
            ];
        }

        $user = User::query()
            ->with(['memberships.tenant'])
            ->where('email', $normalized)
            ->first();

        if ($user === null) {
            return [
                'valid_email' => true,
                'exists' => false,
                'available_for_new_tenant_owner' => true,
                'message' => 'No user account exists for this email. It is available for a new tenant owner.',
            ];
        }

        $released = $this->identifiers->isReleasedEmail($user->email);

        return [
            'valid_email' => true,
            'exists' => true,
            'user' => [
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'original_email' => $user->original_email,
                'status' => $user->status->label(),
                'platform_role' => $user->platform_role?->value,
                'released' => $released,
            ],
            'memberships' => $user->memberships->map(fn ($membership) => [
                'tenant_name' => $membership->tenant->displayName(),
                'tenant_status' => $membership->tenant->status->label(),
                'role' => $membership->role->label(),
                'is_owner' => (bool) $membership->is_owner,
            ])->values()->all(),
            'available_for_new_tenant_owner' => $this->isAvailableForNewTenantOwner($user),
            'message' => $this->summaryMessage($user, $released),
        ];
    }

    private function isAvailableForNewTenantOwner(User $user): bool
    {
        if ($user->platform_role !== null || $user->isPlatformSuperAdmin()) {
            return false;
        }

        if ($this->identifiers->isReleasedEmail($user->email)) {
            return true;
        }

        if ($user->status !== UserStatus::Active) {
            return false;
        }

        return ! $user->memberships()
            ->whereHas('tenant', fn ($query) => $query->where('status', '!=', TenantStatus::Deleted->value))
            ->exists();
    }

    private function summaryMessage(User $user, bool $released): string
    {
        if ($released) {
            return 'User exists with a released email placeholder. The original address is available for reuse.';
        }

        if ($user->isPlatformSuperAdmin()) {
            return 'This email belongs to a platform super admin account.';
        }

        if ($user->status !== UserStatus::Active) {
            return 'User exists but is not active.';
        }

        return 'User account exists and is active.';
    }
}
