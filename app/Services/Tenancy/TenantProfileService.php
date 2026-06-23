<?php

namespace App\Services\Tenancy;

use App\Enums\Audit\AuditAction;
use App\Enums\Tenancy\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TenantProfileService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly TenantIdentifierReleaseService $identifiers,
    ) {}

    /**
     * @param  array{name?: string, legal_name?: ?string, slug?: string, email?: ?string, phone?: ?string}  $attributes
     */
    public function update(Tenant $tenant, array $attributes, ?User $actor = null): Tenant
    {
        return DB::transaction(function () use ($tenant, $attributes, $actor): Tenant {
            $before = $tenant->only(['name', 'legal_name', 'slug', 'email', 'phone', 'original_slug', 'original_email']);
            $changes = [];

            foreach (['name', 'legal_name', 'phone'] as $field) {
                if (array_key_exists($field, $attributes) && $attributes[$field] !== $tenant->{$field}) {
                    $changes[$field] = $attributes[$field];
                }
            }

            if ($tenant->status === TenantStatus::Deleted) {
                if (array_key_exists('slug', $attributes) && $attributes['slug'] !== $tenant->displaySlug()) {
                    if ($this->identifiers->slugInUseByOtherTenant($attributes['slug'], $tenant->id)) {
                        throw ValidationException::withMessages(['slug' => 'This slug is already in use by another tenant.']);
                    }
                    $changes['original_slug'] = $attributes['slug'];
                }

                if (array_key_exists('email', $attributes) && $attributes['email'] !== $tenant->displayEmail()) {
                    if ($this->identifiers->tenantEmailInUseByOther($attributes['email'], $tenant->id)) {
                        throw ValidationException::withMessages(['email' => 'This contact email is already in use by another tenant.']);
                    }
                    $changes['original_email'] = $attributes['email'];
                }
            } else {
                if (array_key_exists('slug', $attributes) && $attributes['slug'] !== $tenant->slug) {
                    if ($this->identifiers->slugInUseByOtherTenant($attributes['slug'], $tenant->id)) {
                        throw ValidationException::withMessages(['slug' => 'This slug is already in use by another tenant.']);
                    }
                    $changes['slug'] = $attributes['slug'];
                }

                if (array_key_exists('email', $attributes) && $attributes['email'] !== $tenant->email) {
                    if ($this->identifiers->tenantEmailInUseByOther($attributes['email'], $tenant->id)) {
                        throw ValidationException::withMessages(['email' => 'This contact email is already in use by another tenant.']);
                    }
                    $changes['email'] = $attributes['email'];
                }
            }

            if ($changes === []) {
                return $tenant;
            }

            $tenant->update($changes);
            $tenant->refresh();

            if ($tenant->identifier_restore_conflict) {
                $resolved = $this->identifiers->resolveIdentifiersForRestore($tenant);
                if (! $resolved['conflict']) {
                    $tenant->update([
                        'slug' => $resolved['slug'],
                        'email' => $resolved['email'],
                        'original_slug' => null,
                        'original_email' => null,
                        'identifier_restore_conflict' => false,
                        'suspension_reason' => $tenant->suspension_reason === 'Restored from deletion with identifier conflicts. Update slug/email before reactivation.'
                            ? 'Restored from deletion.'
                            : $tenant->suspension_reason,
                    ]);
                }
            }

            $this->auditLogger->log(
                AuditAction::TenantUpdated,
                $tenant->fresh(),
                $tenant->id,
                ['before' => $before, 'after' => $tenant->fresh()->only(['name', 'legal_name', 'slug', 'email', 'phone', 'original_slug', 'original_email'])],
                $actor,
            );

            return $tenant->fresh();
        });
    }
}
