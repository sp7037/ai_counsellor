<?php

namespace Tests\Feature;

use App\Enums\Audit\AuditAction;
use App\Enums\Tenancy\TenantRole;
use App\Models\TenantDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class WidgetAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_staff_cannot_create_widget_key(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Staff);
        $this->actingAs($user);
        $this->withTenantContext($user, $tenant);

        Volt::test('tenant.widget.index', ['tenant' => $tenant])
            ->set('keyName', 'Staff attempt')
            ->call('createKey')
            ->assertForbidden();

        $this->assertDatabaseCount('widget_keys', 0);
    }

    public function test_tenant_admin_can_create_widget_key_with_audit(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Admin);
        $this->actingAs($user);
        $this->withTenantContext($user, $tenant);

        Volt::test('tenant.widget.index', ['tenant' => $tenant])
            ->set('keyName', 'Marketing site')
            ->call('createKey')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('widget_keys', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::WidgetKeyCreated->value]);
    }

    public function test_domain_verify_and_remove_are_audited(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Owner);
        $this->actingAs($user);
        $this->withTenantContext($user, $tenant);

        Volt::test('tenant.widget.index', ['tenant' => $tenant])
            ->set('domain', 'clientsite.test')
            ->call('addDomain')
            ->assertHasNoErrors();

        $domain = TenantDomain::query()->first();

        Volt::test('tenant.widget.index', ['tenant' => $tenant])
            ->call('verifyDomain', $domain->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::TenantDomainVerified->value]);

        Volt::test('tenant.widget.index', ['tenant' => $tenant])
            ->call('removeDomain', $domain->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::TenantDomainRemoved->value]);
    }

    public function test_widget_key_rotation_revokes_old_key(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'key' => $key] = $this->createWidgetReadyTenant();
        $this->actingAs($user);
        $this->withTenantContext($user, $tenant);

        Volt::test('tenant.widget.index', ['tenant' => $tenant])
            ->call('rotateKey', $key->uuid)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('widget_keys', [
            'id' => $key->id,
            'status' => 'revoked',
        ]);

        $this->assertDatabaseCount('widget_keys', 2);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::WidgetKeyRotated->value]);
    }

    public function test_guest_cannot_access_widget_admin_page(): void
    {
        ['tenant' => $tenant] = $this->createWidgetReadyTenant();

        $this->get(route('tenant.widget.index', $tenant))
            ->assertRedirect(route('login'));
    }
}
