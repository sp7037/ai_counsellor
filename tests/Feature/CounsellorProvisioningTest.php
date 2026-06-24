<?php

namespace Tests\Feature;

use App\Enums\Billing\EntitlementOutcome;
use App\Enums\Billing\PlanFeature;
use App\Enums\Tenancy\TenantRole;
use App\Enums\Tenancy\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\EntitlementResolver;
use App\Services\Tenancy\TenantLifecycleService;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CounsellorProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlansSeeder::class);
    }

    public function test_pending_tenant_entitlement_message_is_not_suspended(): void
    {
        ['tenant' => $tenant] = $this->createTenantWithMember(
            role: TenantRole::Admin,
            tenantAttributes: ['status' => TenantStatus::Pending->value],
            withSubscription: false,
        );

        $result = app(EntitlementResolver::class)->check($tenant, PlanFeature::CounsellorWorkspace);

        $this->assertSame(EntitlementOutcome::ConfigurationRequired, $result->outcome);
        $this->assertStringContainsString('pending activation', strtolower($result->denyReason()));
        $this->assertStringNotContainsString('suspended', strtolower($result->denyReason()));
    }

    public function test_tenant_admin_can_create_counsellor_after_platform_provisions_tenant(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();

        $this->actingAs($admin);

        Volt::test('platform.tenants.create')
            ->set('name', 'Counsellor Ready Org')
            ->set('slug', 'counsellor-ready-org')
            ->set('create_owner', true)
            ->set('owner_name', 'Org Owner')
            ->set('owner_email', 'org-owner@example.test')
            ->set('owner_password', 'secure-password-12')
            ->call('save')
            ->assertHasNoErrors();

        $tenant = Tenant::query()->where('slug', 'counsellor-ready-org')->firstOrFail();
        $this->assertSame(TenantStatus::Active, $tenant->status);
        $this->assertNotNull($tenant->subscription);

        $owner = User::query()->where('email', 'org-owner@example.test')->firstOrFail();
        $this->actingAs($owner);

        Volt::test('tenant.counsellors.create', ['tenant' => $tenant])
            ->set('name', 'Counsellor One')
            ->set('email', 'counsellor-one@example.test')
            ->set('password', 'secure-password-12')
            ->set('mobile', '7017098399')
            ->set('designation', 'Senior Counsellor')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('tenant.counsellors.index', $tenant));

        $this->assertDatabaseHas('users', ['email' => 'counsellor-one@example.test']);
    }

    public function test_pending_tenant_counsellor_create_shows_activation_message(): void
    {
        ['tenant' => $tenant, 'user' => $admin] = $this->createTenantWithMember(
            role: TenantRole::Admin,
            tenantAttributes: ['status' => TenantStatus::Pending->value],
            withSubscription: false,
        );

        $this->actingAs($admin);

        Volt::test('tenant.counsellors.create', ['tenant' => $tenant])
            ->set('name', 'Counsellor One')
            ->set('email', 'counsellor-one@example.test')
            ->set('password', 'secure-password-12')
            ->call('save')
            ->assertHasErrors('form')
            ->assertHasNoErrors('email');
    }

    public function test_duplicate_email_error_appears_on_email_field_only(): void
    {
        User::factory()->create(['email' => 'existing@example.test']);
        ['tenant' => $tenant, 'user' => $admin] = $this->createTenantWithMember(role: TenantRole::Admin);

        $this->actingAs($admin);

        Volt::test('tenant.counsellors.create', ['tenant' => $tenant])
            ->set('name', 'Counsellor One')
            ->set('email', 'existing@example.test')
            ->set('password', 'secure-password-12')
            ->call('save')
            ->assertHasErrors('email')
            ->assertHasNoErrors('form');
    }

    public function test_pending_tenant_create_page_shows_activation_notice_before_submit(): void
    {
        ['tenant' => $tenant, 'user' => $admin] = $this->createTenantWithMember(
            role: TenantRole::Admin,
            tenantAttributes: ['status' => TenantStatus::Pending->value],
            withSubscription: false,
        );

        $this->actingAs($admin);

        Volt::test('tenant.counsellors.create', ['tenant' => $tenant])
            ->assertSee('pending activation', false)
            ->assertSee('SR Worlds platform administrator', false);
    }

    public function test_activate_and_trial_unblocks_counsellor_creation_for_existing_pending_tenant(): void
    {
        $platformAdmin = User::factory()->platformSuperAdmin()->create();
        ['tenant' => $tenant, 'user' => $admin] = $this->createTenantWithMember(
            role: TenantRole::Admin,
            tenantAttributes: ['status' => TenantStatus::Pending->value],
            withSubscription: false,
        );

        $lifecycle = app(TenantLifecycleService::class);
        $lifecycle->activate($tenant, $platformAdmin);
        $lifecycle->provisionDefaultTrial($tenant->fresh(), $platformAdmin);

        $this->actingAs($admin);

        Volt::test('tenant.counsellors.create', ['tenant' => $tenant->fresh()])
            ->set('name', 'Counsellor Two')
            ->set('email', 'counsellor-two@example.test')
            ->set('password', 'secure-password-12')
            ->call('save')
            ->assertHasNoErrors();
    }
}
