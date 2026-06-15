<?php

namespace App\Services\Widget;

use App\Enums\Audit\AuditAction;
use App\Enums\Widget\TenantDomainStatus;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TenantDomainService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly OriginValidator $originValidator,
    ) {}

    public function add(Tenant $tenant, string $domain, User $actor): TenantDomain
    {
        $normalized = $this->originValidator->normalizeDomain($domain);

        if ($normalized === '') {
            throw ValidationException::withMessages(['domain' => 'Enter a valid domain.']);
        }

        return DB::transaction(function () use ($tenant, $normalized, $actor): TenantDomain {
            $record = TenantDomain::query()->create([
                'tenant_id' => $tenant->id,
                'domain' => $normalized,
                'status' => TenantDomainStatus::Pending->value,
                'created_by' => $actor->id,
            ]);

            $this->auditLogger->log(
                AuditAction::TenantDomainCreated,
                $record,
                $tenant->id,
                ['domain' => $normalized, 'status' => $record->status->value],
                $actor,
            );

            return $record;
        });
    }

    public function verify(TenantDomain $domain, User $actor): TenantDomain
    {
        return DB::transaction(function () use ($domain, $actor): TenantDomain {
            $before = ['status' => $domain->status->value];

            $domain->update([
                'status' => TenantDomainStatus::Verified->value,
                'verified_at' => now(),
            ]);

            $this->auditLogger->log(
                AuditAction::TenantDomainVerified,
                $domain,
                $domain->tenant_id,
                ['before' => $before, 'after' => ['status' => $domain->status->value]],
                $actor,
            );

            return $domain->fresh();
        });
    }

    public function remove(TenantDomain $domain, User $actor): void
    {
        DB::transaction(function () use ($domain, $actor): void {
            $snapshot = [
                'domain' => $domain->domain,
                'status' => $domain->status->value,
            ];

            $domain->delete();

            $this->auditLogger->log(
                AuditAction::TenantDomainRemoved,
                null,
                $domain->tenant_id,
                ['before' => $snapshot],
                $actor,
            );
        });
    }
}
