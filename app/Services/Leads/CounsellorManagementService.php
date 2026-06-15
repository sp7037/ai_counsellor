<?php

namespace App\Services\Leads;

use App\Enums\Audit\AuditAction;
use App\Enums\Tenancy\TenantRole;
use App\Models\CounsellorProfile;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Tenancy\MembershipLifecycleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CounsellorManagementService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly MembershipLifecycleService $memberships,
    ) {}

    /**
     * @param  array<string, mixed>  $profile
     */
    public function create(Tenant $tenant, array $input, array $profile, User $actor): TenantMembership
    {
        return DB::transaction(function () use ($tenant, $input, $profile, $actor): TenantMembership {
            $email = strtolower(trim((string) $input['email']));

            if (User::query()->where('email', $email)->exists()) {
                throw ValidationException::withMessages(['email' => 'A user with this email already exists.']);
            }

            $user = User::query()->create([
                'name' => trim((string) $input['name']),
                'email' => $email,
                'password' => Hash::make((string) $input['password']),
            ]);

            $membership = $this->memberships->addMember(
                $tenant,
                $user,
                TenantRole::Staff,
                false,
                $actor,
            );

            CounsellorProfile::query()->create([
                'tenant_id' => $tenant->id,
                'membership_id' => $membership->id,
                'mobile' => $profile['mobile'] ?? null,
                'designation' => $profile['designation'] ?? null,
                'max_active_leads' => $profile['max_active_leads'] ?? null,
                'timezone' => $profile['timezone'] ?? $tenant->timezone,
            ]);

            $this->audit->log(
                AuditAction::CounsellorCreated,
                $membership,
                $tenant->id,
                ['user_id' => $user->id],
                $actor,
            );

            return $membership->load('user');
        });
    }
}
