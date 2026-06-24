<?php

namespace Tests\Feature;

use App\Enums\Tenancy\MembershipStatus;
use App\Enums\Tenancy\TenantRole;
use App\Enums\Tenancy\TenantStatus;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Auth\PostLoginRedirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PostLoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_redirects_to_platform_overview(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();

        $this->assertSame(
            route('platform.overview'),
            app(PostLoginRedirect::class)->intendedUrl($admin),
        );
    }

    public function test_single_active_tenant_owner_redirects_to_tenant_dashboard(): void
    {
        ['tenant' => $tenant, 'user' => $owner] = $this->createTenantWithMember(role: TenantRole::Owner);

        $this->assertSame(
            route('tenant.dashboard', $tenant),
            app(PostLoginRedirect::class)->intendedUrl($owner),
        );
    }

    public function test_pending_tenant_owner_redirects_to_tenant_dashboard(): void
    {
        $tenant = Tenant::factory()->create(['status' => TenantStatus::Pending->value]);
        $owner = User::factory()->create();

        TenantMembership::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'role' => TenantRole::Owner->value,
            'status' => MembershipStatus::Active->value,
            'is_owner' => true,
        ]);

        $this->assertSame(
            route('tenant.dashboard', $tenant),
            app(PostLoginRedirect::class)->intendedUrl($owner),
        );
    }

    public function test_suspended_tenant_member_redirects_to_tenant_dashboard(): void
    {
        ['tenant' => $tenant, 'user' => $admin] = $this->createTenantWithMember(
            role: TenantRole::Admin,
            tenantAttributes: [
                'status' => TenantStatus::Suspended->value,
                'suspended_at' => now(),
                'suspension_reason' => 'Review',
            ],
        );

        $this->assertSame(
            route('tenant.dashboard', $tenant),
            app(PostLoginRedirect::class)->intendedUrl($admin),
        );
    }

    public function test_multi_tenant_user_redirects_to_tenant_select(): void
    {
        ['user' => $user] = $this->createTenantWithMember(role: TenantRole::Admin);
        $this->createTenantWithMember([], $user, TenantRole::Admin);

        $this->assertSame(
            route('tenant.select'),
            app(PostLoginRedirect::class)->intendedUrl($user),
        );
    }

    public function test_user_without_accessible_tenant_redirects_to_tenant_select(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['status' => TenantStatus::Deleted->value]);

        TenantMembership::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => TenantRole::Owner->value,
            'status' => MembershipStatus::Active->value,
            'is_owner' => true,
        ]);

        $this->assertSame(
            route('tenant.select'),
            app(PostLoginRedirect::class)->intendedUrl($user),
        );
    }

    public function test_dashboard_route_redirects_authenticated_tenant_owner(): void
    {
        ['tenant' => $tenant, 'user' => $owner] = $this->createTenantWithMember(role: TenantRole::Owner);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertRedirect(route('tenant.dashboard', $tenant));
    }

    public function test_authenticated_homepage_links_to_workspace_not_home(): void
    {
        ['tenant' => $tenant, 'user' => $owner] = $this->createTenantWithMember(role: TenantRole::Owner);

        $this->actingAs($owner)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Go to Dashboard', false)
            ->assertSee(route('tenant.dashboard', $tenant), false);
    }

    public function test_tenant_owner_login_reaches_dashboard(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();

        $this->actingAs($admin);

        Volt::test('platform.tenants.create')
            ->set('name', 'Live Test Org')
            ->set('slug', 'live-test-org')
            ->set('create_owner', true)
            ->set('owner_name', 'Live Owner')
            ->set('owner_email', 'live-owner@example.test')
            ->set('owner_password', 'secure-password-12')
            ->call('save')
            ->assertHasNoErrors();

        $tenant = Tenant::query()->where('slug', 'live-test-org')->firstOrFail();
        $owner = User::query()->where('email', 'live-owner@example.test')->firstOrFail();
        $owner->forceFill([
            'password' => Hash::make('secure-password-12'),
            'email_verified_at' => now(),
        ])->save();

        $this->post(route('logout'));

        Volt::test('auth.login')
            ->set('email', 'live-owner@example.test')
            ->set('password', 'secure-password-12')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertSame(
            route('tenant.dashboard', $tenant),
            app(PostLoginRedirect::class)->intendedUrl($owner->fresh()),
        );
    }

    public function test_subscription_page_loads_when_plan_change_requests_table_is_missing(): void
    {
        ['tenant' => $tenant, 'user' => $admin] = $this->createTenantWithMember(role: TenantRole::Admin);

        Schema::dropIfExists('tenant_plan_change_requests');

        $this->actingAs($admin)
            ->get(route('tenant.subscription', $tenant))
            ->assertOk()
            ->assertDontSee('Request plan change', false);

        $this->artisan('migrate', ['--force' => true]);
    }

    public function test_tenant_select_shows_friendly_message_when_no_workspace_available(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create(['status' => TenantStatus::Archived->value]);

        TenantMembership::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => TenantRole::Owner->value,
            'status' => MembershipStatus::Active->value,
            'is_owner' => true,
        ]);

        $this->actingAs($user)
            ->get(route('tenant.select'))
            ->assertOk()
            ->assertSee('No organisation workspace is available', false);
    }
}
