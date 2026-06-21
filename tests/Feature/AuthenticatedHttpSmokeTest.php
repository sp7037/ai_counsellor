<?php

namespace Tests\Feature;

use App\Enums\Tenancy\MembershipStatus;
use App\Enums\Tenancy\TenantRole;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AuthenticatedHttpSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_livewire_login_flow_reaches_platform_and_tenant_pages(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create([
            'password' => Hash::make('smoke-test-password-12'),
        ]);

        ['tenant' => $tenant, 'user' => $owner] = $this->createTenantWithMember(role: TenantRole::Owner);
        $owner->update(['password' => Hash::make('smoke-test-password-12')]);

        Volt::test('auth.login')
            ->set('email', $admin->email)
            ->set('password', 'smoke-test-password-12')
            ->call('login')
            ->assertHasNoErrors();

        $this->get(route('platform.tenants.index'))->assertOk();
        $this->get(route('platform.overview'))->assertOk();
        $this->get(route('platform.ai-operations.index'))->assertOk();
        $this->get(route('platform.usage.index'))->assertOk();
        $this->get(route('platform.audit-logs.index'))->assertOk();
        $this->get(route('platform.settings.index'))->assertOk();
        $this->get(route('platform.system-health.index'))->assertOk();
        $this->get(route('platform.tenants.show', $tenant))->assertOk();
        $this->get(route('platform.tenants.subscription', $tenant))->assertOk();
        $this->get(route('platform.plans.index'))->assertOk();
        $this->get(route('platform.integrations.index'))->assertOk();

        $this->post(route('logout'));

        Volt::test('auth.login')
            ->set('email', $owner->email)
            ->set('password', 'smoke-test-password-12')
            ->call('login')
            ->assertHasNoErrors();

        $this->get(route('tenant.select'))->assertOk();
        $this->get(route('tenant.dashboard', $tenant))->assertOk();
        $this->get(route('tenant.subscription', $tenant))->assertOk();
        $this->get(route('tenant.members.index', $tenant))->assertOk();
        $this->get(route('tenant.notes.index', $tenant))->assertOk();
        $this->get(route('tenant.widget.index', $tenant))->assertOk();
        $this->get(route('tenant.widget.conversations', $tenant))->assertOk();
        $this->get(route('tenant.configuration.index', $tenant))->assertOk();
        $this->get(route('tenant.configuration.branding', $tenant))->assertOk();
        $this->get(route('tenant.knowledge.index', $tenant))->assertOk();
        $this->get(route('tenant.knowledge.items', $tenant))->assertOk();
        $this->get(route('tenant.knowledge.fees', $tenant))->assertOk();
        $this->get(route('tenant.knowledge.eligibility', $tenant))->assertOk();
        $this->get(route('tenant.knowledge.documents', $tenant))->assertOk();
        $this->get(route('tenant.knowledge.course-institutions', $tenant))->assertOk();
        $this->get(route('tenant.ai.configuration', $tenant))->assertOk();
        $this->get(route('tenant.integrations.index', $tenant))->assertOk();
        $this->get(route('tenant.integrations.whatsapp', $tenant))->assertOk();
        $this->get(route('tenant.leads.index', $tenant))->assertOk();
        $this->get(route('tenant.counsellors.index', $tenant))->assertOk();
        $this->get(route('tenant.conversations.index', $tenant))->assertOk();

        $counsellor = User::factory()->create(['password' => Hash::make('smoke-test-password-12')]);
        TenantMembership::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $counsellor->id,
            'role' => TenantRole::Staff->value,
            'status' => MembershipStatus::Active->value,
        ]);

        $this->post(route('logout'));

        Volt::test('auth.login')
            ->set('email', $counsellor->email)
            ->set('password', 'smoke-test-password-12')
            ->call('login')
            ->assertHasNoErrors();

        $this->get(route('workspace.dashboard', $tenant))->assertOk();
        $this->get(route('workspace.leads.index', $tenant))->assertOk();
        $this->get(route('workspace.follow-ups.index', $tenant))->assertOk();
        $this->get(route('workspace.conversations.index', $tenant))->assertOk();
    }

    public function test_unauthorized_http_access_is_denied(): void
    {
        ['tenant' => $tenantA, 'user' => $owner] = $this->createTenantWithMember(role: TenantRole::Owner);
        ['tenant' => $tenantB] = $this->createTenantWithMember();
        $owner->update(['password' => Hash::make('smoke-test-password-12')]);

        Volt::test('auth.login')
            ->set('email', $owner->email)
            ->set('password', 'smoke-test-password-12')
            ->call('login');

        $this->get(route('platform.tenants.index'))->assertForbidden();
        $this->get(route('tenant.dashboard', $tenantB))->assertForbidden();
        $this->get(route('tenant.leads.index', $tenantB))->assertForbidden();
    }
}
