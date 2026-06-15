<?php

namespace Tests\Feature;

use App\Enums\Audit\AuditAction;
use App\Enums\PlatformRole;
use App\Enums\Tenancy\MembershipStatus;
use App\Enums\Tenancy\TenantRole;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Tenancy\MembershipLifecycleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MembershipLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_role_change_succeeds_and_creates_audit_record(): void
    {
        ['tenant' => $tenant, 'user' => $owner] = $this->createTenantWithMember(role: TenantRole::Owner);
        $staff = User::factory()->create();
        $membership = TenantMembership::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $staff->id,
            'role' => TenantRole::Staff->value,
        ]);

        $this->actingAs($owner);

        $updated = app(MembershipLifecycleService::class)->changeRole(
            $membership,
            TenantRole::Admin,
            $owner,
        );

        $this->assertSame(TenantRole::Admin, $updated->role);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::MembershipRoleChanged->value,
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_unauthorized_role_change_fails_without_audit_record(): void
    {
        ['tenant' => $tenant, 'user' => $staffUser] = $this->createTenantWithMember(role: TenantRole::Staff);
        $target = User::factory()->create();
        $membership = TenantMembership::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $target->id,
            'role' => TenantRole::Staff->value,
        ]);

        $this->actingAs($staffUser);

        $this->expectException(AuthorizationException::class);

        try {
            app(MembershipLifecycleService::class)->changeRole($membership, TenantRole::Admin, $staffUser);
        } finally {
            $this->assertDatabaseMissing('audit_logs', [
                'action' => AuditAction::MembershipRoleChanged->value,
                'tenant_id' => $tenant->id,
            ]);
        }
    }

    public function test_status_change_creates_audit_record(): void
    {
        ['tenant' => $tenant, 'user' => $owner] = $this->createTenantWithMember(role: TenantRole::Owner);
        $staff = User::factory()->create();
        $membership = TenantMembership::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $staff->id,
            'role' => TenantRole::Staff->value,
            'status' => MembershipStatus::Active->value,
        ]);

        $this->actingAs($owner);

        app(MembershipLifecycleService::class)->changeStatus(
            $membership,
            MembershipStatus::Inactive,
            $owner,
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::MembershipStatusChanged->value,
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_removal_creates_audit_record(): void
    {
        ['tenant' => $tenant, 'user' => $owner] = $this->createTenantWithMember(role: TenantRole::Owner);
        $staff = User::factory()->create();
        $membership = TenantMembership::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $staff->id,
            'role' => TenantRole::Staff->value,
        ]);

        $this->actingAs($owner);

        app(MembershipLifecycleService::class)->removeMember($membership, $owner);

        $this->assertDatabaseMissing('tenant_user', ['id' => $membership->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::MembershipRemoved->value,
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_final_active_owner_cannot_be_deactivated(): void
    {
        ['tenant' => $tenant, 'membership' => $ownerMembership] = $this->createTenantWithMember(role: TenantRole::Owner);
        $admin = User::factory()->create();
        TenantMembership::factory()->admin()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
        ]);

        $this->actingAs($admin);

        $this->expectException(ValidationException::class);
        app(MembershipLifecycleService::class)->changeStatus($ownerMembership, MembershipStatus::Inactive, $admin);
    }

    public function test_final_active_owner_cannot_be_removed(): void
    {
        ['membership' => $ownerMembership] = $this->createTenantWithMember(role: TenantRole::Owner);
        $platformAdmin = User::factory()->platformSuperAdmin()->create();

        $this->actingAs($platformAdmin);

        $this->expectException(ValidationException::class);
        app(MembershipLifecycleService::class)->removeMember($ownerMembership, $platformAdmin);
    }

    public function test_tenant_role_changes_cannot_alter_users_platform_role(): void
    {
        ['tenant' => $tenant, 'user' => $owner] = $this->createTenantWithMember(role: TenantRole::Owner);
        $platformUser = User::factory()->platformSuperAdmin()->create();
        $membership = TenantMembership::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $platformUser->id,
            'role' => TenantRole::Staff->value,
        ]);

        $this->actingAs($owner);

        $this->expectException(ValidationException::class);
        app(MembershipLifecycleService::class)->changeRole($membership, TenantRole::Admin, $owner);

        $this->assertSame(PlatformRole::SuperAdmin, $platformUser->fresh()->platform_role);
    }

    public function test_tenant_admin_cannot_promote_user_to_owner(): void
    {
        ['tenant' => $tenant, 'user' => $admin] = $this->createTenantWithMember(role: TenantRole::Admin);
        $target = User::factory()->create();
        $membership = TenantMembership::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $target->id,
            'role' => TenantRole::Staff->value,
        ]);

        $this->actingAs($admin);

        $this->expectException(ValidationException::class);
        app(MembershipLifecycleService::class)->changeRole($membership, TenantRole::Owner, $admin);
    }

    public function test_unauthorized_policy_role_change_is_denied(): void
    {
        ['tenant' => $tenant, 'user' => $staffUser] = $this->createTenantWithMember(role: TenantRole::Staff);
        $target = User::factory()->create();
        $membership = TenantMembership::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $target->id,
        ]);

        $this->actingAs($staffUser);

        $this->assertFalse(Gate::forUser($staffUser)->allows('updateRole', $membership));
    }
}
