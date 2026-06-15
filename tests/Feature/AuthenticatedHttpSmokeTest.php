<?php

namespace Tests\Feature;

use App\Enums\Tenancy\TenantRole;
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
        $this->get(route('platform.tenants.show', $tenant))->assertOk();

        $this->post(route('logout'));

        Volt::test('auth.login')
            ->set('email', $owner->email)
            ->set('password', 'smoke-test-password-12')
            ->call('login')
            ->assertHasNoErrors();

        $this->get(route('tenant.select'))->assertOk();
        $this->get(route('tenant.dashboard', $tenant))->assertOk();
        $this->get(route('tenant.members.index', $tenant))->assertOk();
        $this->get(route('tenant.notes.index', $tenant))->assertOk();
        $this->get(route('tenant.widget.index', $tenant))->assertOk();
        $this->get(route('tenant.widget.conversations', $tenant))->assertOk();
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
    }
}
