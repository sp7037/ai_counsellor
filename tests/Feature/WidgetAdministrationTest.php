<?php

namespace Tests\Feature;

use App\Enums\Audit\AuditAction;
use App\Enums\Tenancy\TenantRole;
use App\Enums\Widget\TenantDomainStatus;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Services\Tenancy\TenantContext;
use App\Services\Widget\TenantDomainService;
use App\Services\Widget\WidgetKeyService;
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

    public function test_tenant_admin_can_create_widget_key_without_tenant_context(): void
    {
        app(TenantContext::class)->clear();

        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Admin);
        $this->actingAs($user);

        Volt::test('tenant.widget.index', ['tenant' => $tenant])
            ->set('keyName', 'Production widget')
            ->call('createKey')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('widget_keys', [
            'tenant_id' => $tenant->id,
            'name' => 'Production widget',
        ]);
    }

    public function test_widget_key_service_persists_tenant_id_without_tenant_context(): void
    {
        app(TenantContext::class)->clear();

        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Admin);

        $key = app(WidgetKeyService::class)->create($tenant, 'API key', $user);

        $this->assertSame($tenant->id, $key->tenant_id);
        $this->assertDatabaseHas('widget_keys', [
            'id' => $key->id,
            'tenant_id' => $tenant->id,
            'name' => 'API key',
        ]);
    }

    public function test_tenant_admin_can_add_domain_without_tenant_context(): void
    {
        app(TenantContext::class)->clear();

        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Owner);
        $this->actingAs($user);

        Volt::test('tenant.widget.index', ['tenant' => $tenant])
            ->set('domain', 'clientsite.test')
            ->call('addDomain')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tenant_domains', [
            'tenant_id' => $tenant->id,
            'domain' => 'clientsite.test',
            'status' => TenantDomainStatus::Verified->value,
        ]);
    }

    public function test_adding_www_domain_stores_canonical_apex_domain(): void
    {
        app(TenantContext::class)->clear();

        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Owner);
        $this->actingAs($user);

        Volt::test('tenant.widget.index', ['tenant' => $tenant])
            ->set('domain', 'www.srworlds.in')
            ->call('addDomain')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tenant_domains', [
            'tenant_id' => $tenant->id,
            'domain' => 'srworlds.in',
            'status' => TenantDomainStatus::Verified->value,
        ]);
    }

    public function test_widget_session_accepts_verified_production_domain(): void
    {
        app(TenantContext::class)->clear();

        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Owner);
        $key = app(WidgetKeyService::class)->create($tenant, 'Site widget', $user);
        app(TenantDomainService::class)->add($tenant, 'www.srworlds.in', $user);

        foreach (['https://srworlds.in', 'https://www.srworlds.in'] as $origin) {
            $this->postJson('/widget/v1/session', [
                'widget_key' => $key->public_key,
            ], [
                'Origin' => $origin,
            ])->assertOk();
        }
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

        $domain = $this->tenantScopedDomain($tenant, 'clientsite.test');

        $this->assertSame(TenantDomainStatus::Verified, $domain->status);

        Volt::test('tenant.widget.index', ['tenant' => $tenant])
            ->call('removeDomain', $domain->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::TenantDomainRemoved->value]);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::TenantDomainVerified->value]);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::TenantDomainCreated->value]);
    }

    private function tenantScopedDomain(Tenant $tenant, string $domain): TenantDomain
    {
        return TenantDomain::query()
            ->where('tenant_id', $tenant->id)
            ->where('domain', $domain)
            ->firstOrFail();
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
