<?php

namespace Tests\Feature;

use App\Enums\AI\AiRunStatus;
use App\Enums\Audit\AuditAction;
use App\Enums\Tenancy\TenantRole;
use App\Enums\Tenancy\TenantStatus;
use App\Models\AiRun;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visitor;
use App\Services\Auth\PostLoginRedirect;
use App\Services\Platform\PlatformAiOperationsService;
use App\Services\Platform\PlatformSettingsService;
use App\Services\Platform\PlatformUsageReportingService;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PlatformControlPlaneTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_platform_routes(): void
    {
        $this->get(route('platform.overview'))->assertRedirect(route('login'));
        $this->get(route('platform.tenants.index'))->assertRedirect(route('login'));
        $this->get(route('platform.ai-operations.index'))->assertRedirect(route('login'));
    }

    public function test_unverified_super_admin_is_redirected_from_platform_routes(): void
    {
        $admin = User::factory()->platformSuperAdmin()->unverified()->create();

        $this->actingAs($admin)
            ->get(route('platform.overview'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_tenant_admin_and_staff_are_denied_platform_access(): void
    {
        ['user' => $admin] = $this->createTenantWithMember(role: TenantRole::Admin);
        ['user' => $staff] = $this->createTenantWithMember(role: TenantRole::Staff);

        $this->actingAs($admin)->get(route('platform.overview'))->assertForbidden();
        $this->actingAs($staff)->get(route('platform.usage.index'))->assertForbidden();
    }

    public function test_super_admin_can_access_all_platform_pages(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();

        $routes = [
            'platform.overview',
            'platform.tenants.index',
            'platform.tenants.create',
            'platform.ai-operations.index',
            'platform.usage.index',
            'platform.audit-logs.index',
            'platform.settings.index',
            'platform.failed-runs.index',
            'platform.system-health.index',
        ];

        foreach ($routes as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }
    }

    public function test_super_admin_login_redirects_to_platform_overview(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();

        $this->assertSame(
            route('platform.overview'),
            app(PostLoginRedirect::class)->intendedUrl($admin),
        );
    }

    public function test_verified_super_admin_email_verification_redirects_to_platform_overview(): void
    {
        $admin = User::factory()->platformSuperAdmin()->unverified()->create();

        $this->actingAs($admin)
            ->get(route('verification.notice'))
            ->assertOk()
            ->assertSee('Platform Super Admin control plane', false);

        $admin->markEmailAsVerified();

        Volt::test('auth.verify-email')
            ->call('sendVerification')
            ->assertRedirect(route('platform.overview'));
    }

    public function test_tenant_list_supports_search_and_filter(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        Tenant::factory()->active()->create(['name' => 'Alpha Org', 'slug' => 'alpha-org']);
        Tenant::factory()->create(['name' => 'Beta Org', 'slug' => 'beta-org', 'status' => TenantStatus::Suspended->value]);

        $this->actingAs($admin);

        Volt::test('platform.tenants.index')
            ->set('search', 'Alpha')
            ->assertSee('Alpha Org')
            ->assertDontSee('Beta Org');

        Volt::test('platform.tenants.index')
            ->set('status', TenantStatus::Suspended->value)
            ->assertSee('Beta Org')
            ->assertDontSee('Alpha Org');
    }

    public function test_suspend_tenant_requires_reason_and_records_audit(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        $tenant = Tenant::factory()->active()->create();

        $this->actingAs($admin);

        Volt::test('platform.tenants.show', ['tenant' => $tenant])
            ->set('confirm_suspend', true)
            ->set('suspension_reason', '')
            ->call('suspend')
            ->assertHasErrors('suspension_reason');

        Volt::test('platform.tenants.show', ['tenant' => $tenant])
            ->set('confirm_suspend', true)
            ->set('suspension_reason', 'Billing dispute')
            ->call('suspend')
            ->assertHasNoErrors();

        $tenant->refresh();
        $this->assertSame(TenantStatus::Suspended, $tenant->status);
        $this->assertSame('Billing dispute', $tenant->suspension_reason);
        $this->assertSame($admin->id, $tenant->suspended_by);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::TenantSuspended->value]);
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id]);
    }

    public function test_suspended_tenant_member_can_still_access_dashboard(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Owner);
        app(TenantLifecycleService::class)->suspend($tenant, 'Test suspension', User::factory()->platformSuperAdmin()->create());

        $this->actingAs($user)
            ->get(route('tenant.dashboard', $tenant->fresh()))
            ->assertOk();
    }

    public function test_ai_operations_page_does_not_expose_secrets(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Owner);
        $this->configureTenantAi($tenant, $user, [
            'credential_mode' => 'tenant_key_required',
            'api_key' => 'sk-test-secret-key-12345',
        ]);

        $run = $this->createPlatformAiRun($tenant, [
            'status' => AiRunStatus::Failed->value,
            'error_category' => 'provider_error',
            'credential_source' => 'tenant',
        ]);

        $response = $this->actingAs($admin)->get(route('platform.ai-operations.index'));
        $response->assertOk();
        $response->assertDontSee('sk-test-secret-key-12345', false);
        $response->assertSee('provider_error', false);

        $detail = app(PlatformAiOperationsService::class)->safeRunDetail($run);
        $encoded = json_encode($detail);
        $this->assertStringNotContainsString('sk-test', $encoded ?: '');
    }

    public function test_usage_reporting_aggregates_tokens_correctly(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        ['tenant' => $tenant] = $this->createTenantWithMember();

        $this->createPlatformAiRun($tenant, [
            'status' => AiRunStatus::Success->value,
            'input_tokens' => 10,
            'output_tokens' => 20,
            'total_tokens' => 30,
        ]);

        $summary = app(PlatformUsageReportingService::class)->periodSummary();
        $this->assertSame(1, $summary['successful_runs']);
        $this->assertSame(30, $summary['total_tokens']);

        $this->actingAs($admin)->get(route('platform.usage.index'))->assertOk()->assertSee('30', false);
    }

    public function test_platform_settings_never_return_api_key_to_browser(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();

        app(PlatformSettingsService::class)->update([
            'platform_api_key' => 'sk-platform-secret-999',
        ], $admin);

        $this->actingAs($admin)
            ->get(route('platform.settings.index'))
            ->assertOk()
            ->assertDontSee('sk-platform-secret-999', false)
            ->assertSee('Configured (value not shown)', false);
    }

    public function test_platform_settings_update_is_audited(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();

        $this->actingAs($admin);

        Volt::test('platform.settings.index')
            ->set('support_email', 'ops@example.test')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::PlatformSettingsUpdated->value,
            'actor_user_id' => $admin->id,
        ]);
    }

    public function test_platform_settings_can_store_widget_powered_by_configuration(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        $this->actingAs($admin);

        Volt::test('platform.settings.index')
            ->set('widget_powered_by_enabled', true)
            ->set('widget_powered_by_label', 'Powered by SR Worlds AI')
            ->set('widget_powered_by_logo_url', 'https://cdn.example.test/platform-logo.png')
            ->set('widget_launcher_logo_url', 'https://cdn.example.test/launcher-logo.png')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('platform_settings', ['key' => 'widget_powered_by_enabled']);
        $this->assertDatabaseHas('platform_settings', ['key' => 'widget_powered_by_label']);
        $this->assertDatabaseHas('platform_settings', ['key' => 'widget_powered_by_logo_url']);
        $this->assertDatabaseHas('platform_settings', ['key' => 'widget_launcher_logo_url']);
    }

    public function test_audit_log_viewer_is_read_only(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        AuditLog::query()->create([
            'action' => AuditAction::TenantCreated->value,
            'actor_user_id' => $admin->id,
            'metadata' => ['name' => 'Test'],
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('platform.audit-logs.index'))
            ->assertOk()
            ->assertSee('Tenant created', false);
    }

    public function test_platform_queries_do_not_leak_tenant_context(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        ['tenant' => $tenantA, 'user' => $userA] = $this->createTenantWithMember();
        ['tenant' => $tenantB] = $this->createTenantWithMember();

        $this->withTenantContext($userA, $tenantA);
        $this->assertSame($tenantA->id, app(TenantContext::class)->tenantId());

        $this->actingAs($admin)->get(route('platform.overview'))->assertOk();

        app(TenantContext::class)->clear();
        $this->assertNull(app(TenantContext::class)->tenant());
    }

    public function test_platform_layout_includes_mobile_navigation(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();

        $this->actingAs($admin)
            ->get(route('platform.overview'))
            ->assertOk()
            ->assertSee('platform-mobile-nav', false)
            ->assertSee('Platform Super Admin', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPlatformAiRun(Tenant $tenant, array $overrides = []): AiRun
    {
        $visitor = Visitor::withoutGlobalScopes()->forceCreate([
            'tenant_id' => $tenant->id,
            'fingerprint_hash' => hash('sha256', (string) Str::uuid()),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $conversation = Conversation::withoutGlobalScopes()->forceCreate([
            'tenant_id' => $tenant->id,
            'visitor_id' => $visitor->id,
            'channel' => 'widget',
            'status' => 'open',
            'started_at' => now(),
        ]);

        return AiRun::withoutGlobalScopes()->forceCreate(array_merge([
            'tenant_id' => $tenant->id,
            'conversation_id' => $conversation->id,
            'request_uuid' => (string) Str::uuid(),
            'provider' => 'fake',
            'model' => 'fake-model',
            'status' => AiRunStatus::Success->value,
            'attempt_number' => 1,
        ], $overrides));
    }
}
