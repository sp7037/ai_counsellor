<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\TenantSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WidgetPublicConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_session_includes_public_configuration_without_internal_ids(): void
    {
        ['key' => $key] = $this->createWidgetReadyTenant();

        $response = $this->postJson('/widget/v1/session', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertOk();

        $config = $response->json('configuration');

        $this->assertArrayHasKey('branding', $config);
        $this->assertArrayHasKey('catalogue', $config);
        $this->assertArrayHasKey('assistant_name', $config['branding']);
        $this->assertArrayHasKey('logo_url', $config['branding']);
        $this->assertArrayHasKey('powered_by', $config);
        $this->assertArrayNotHasKey('tenant_id', $config);
        $this->assertArrayNotHasKey('id', $config['branding'] ?? []);
        $this->assertFalse(str_contains((string) ($config['branding']['logo_url'] ?? ''), storage_path()));
    }

    public function test_widget_config_endpoint_returns_only_public_fields(): void
    {
        ['key' => $key] = $this->createWidgetReadyTenant();

        $token = $this->postJson('/widget/v1/session', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->json('session_token');

        $response = $this->getJson('/widget/v1/config', [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $config = $response->json('configuration');
        $encoded = json_encode($config);

        $this->assertStringNotContainsString('tenant_id', (string) $encoded);
        $this->assertStringNotContainsString('password', (string) $encoded);
        $this->assertArrayHasKey('availability', $config);
        $this->assertArrayHasKey('assistant_name', $config['branding']);
        $this->assertArrayHasKey('enabled', $config['powered_by']);
    }

    public function test_widget_config_returns_safe_public_tenant_logo_url_or_null(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'key' => $key] = $this->createWidgetReadyTenant();
        $this->withTenantContext($user, $tenant);

        $settings = TenantSettings::query()->firstOrNew(['tenant_id' => $tenant->id]);
        $settings->tenant()->associate($tenant);
        $settings->display_name = $tenant->name;
        $settings->logo_path = 'tenant-logos/'.$tenant->uuid.'/logo.png';
        $settings->save();

        $config = $this->postJson('/widget/v1/session', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertOk()->json('configuration');

        $logoUrl = (string) ($config['branding']['logo_url'] ?? '');
        $this->assertNotSame('', $logoUrl);
        $this->assertStringNotContainsString(storage_path(), $logoUrl);
        $this->assertStringContainsString('/storage/', $logoUrl);
    }

    public function test_widget_config_prefers_assistant_name_over_display_name(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'key' => $key] = $this->createWidgetReadyTenant();
        $this->withTenantContext($user, $tenant);

        $settings = TenantSettings::query()->firstOrNew(['tenant_id' => $tenant->id]);
        $settings->tenant()->associate($tenant);
        $settings->display_name = 'Tenant Business';
        $settings->assistant_name = 'Admissions Guide';
        $settings->save();

        $assistantName = $this->postJson('/widget/v1/session', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertOk()->json('configuration.branding.assistant_name');

        $this->assertSame('Admissions Guide', $assistantName);
    }

    public function test_widget_config_uses_platform_powered_by_settings_safely(): void
    {
        ['key' => $key] = $this->createWidgetReadyTenant();

        PlatformSetting::query()->updateOrCreate(['key' => 'widget_powered_by_enabled'], ['value' => true]);
        PlatformSetting::query()->updateOrCreate(['key' => 'widget_powered_by_label'], ['value' => 'Powered by Example Platform']);
        PlatformSetting::query()->updateOrCreate(['key' => 'widget_powered_by_logo_url'], ['value' => 'https://cdn.example.test/powered.png']);

        $config = $this->postJson('/widget/v1/session', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertOk()->json('configuration');

        $this->assertTrue((bool) $config['powered_by']['enabled']);
        $this->assertSame('Powered by Example Platform', $config['powered_by']['label']);
        $this->assertSame('https://cdn.example.test/powered.png', $config['powered_by']['logo_url']);
    }

    public function test_widget_config_returns_super_admin_launcher_logo_url(): void
    {
        ['key' => $key] = $this->createWidgetReadyTenant();

        PlatformSetting::query()->updateOrCreate(['key' => 'widget_launcher_logo_url'], ['value' => 'https://cdn.example.test/launcher.png']);

        $config = $this->postJson('/widget/v1/session', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertOk()->json('configuration');

        $this->assertArrayHasKey('launcher', $config);
        $this->assertSame('https://cdn.example.test/launcher.png', $config['launcher']['logo_url']);
        $this->assertSame('platform', $config['launcher']['source']);
        $this->assertStringNotContainsString(storage_path(), (string) $config['launcher']['logo_url']);
    }

    public function test_widget_bootstrap_returns_launcher_logo_without_session(): void
    {
        ['key' => $key] = $this->createWidgetReadyTenant();

        PlatformSetting::query()->updateOrCreate(['key' => 'widget_launcher_logo_url'], ['value' => 'https://cdn.example.test/launcher.png']);

        $response = $this->postJson('/widget/v1/bootstrap', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertOk();

        $config = $response->json('configuration');

        $this->assertArrayHasKey('launcher', $config);
        $this->assertSame('https://cdn.example.test/launcher.png', $config['launcher']['logo_url']);
        $this->assertSame('platform', $config['launcher']['source']);
        $this->assertSame('Ask AI Counsellor', $config['launcher']['teaser_text']);
        $this->assertArrayHasKey('branding', $config);
        $this->assertArrayHasKey('powered_by', $config);

        // The bootstrap endpoint is session-less and leaks no internal identifiers.
        $this->assertNull($response->json('session_token'));
        $this->assertStringNotContainsString('tenant_id', (string) json_encode($config));
        $this->assertStringNotContainsString(storage_path(), (string) json_encode($config));
    }

    public function test_launcher_logo_is_platform_owned_and_distinct_from_tenant_header_logo(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'key' => $key] = $this->createWidgetReadyTenant();
        $this->withTenantContext($user, $tenant);

        $settings = TenantSettings::query()->firstOrNew(['tenant_id' => $tenant->id]);
        $settings->tenant()->associate($tenant);
        $settings->display_name = $tenant->name;
        $settings->logo_path = 'tenant-logos/'.$tenant->uuid.'/logo.png';
        $settings->save();

        // No Super Admin launcher logo configured -> launcher uses the bundled platform logo.
        $config = $this->postJson('/widget/v1/session', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertOk()->json('configuration');

        $launcherUrl = (string) ($config['launcher']['logo_url'] ?? '');
        $tenantHeaderUrl = (string) ($config['branding']['logo_url'] ?? '');

        // Launcher logo is platform-owned: a safe public URL, not the tenant storage logo.
        $this->assertNotSame('', $launcherUrl);
        $this->assertStringNotContainsString(storage_path(), $launcherUrl);
        $this->assertStringNotContainsString('/storage/', $launcherUrl);

        // Tenant header logo remains tenant-owned and distinct from the launcher logo.
        $this->assertStringContainsString('/storage/', $tenantHeaderUrl);
        $this->assertNotSame($tenantHeaderUrl, $launcherUrl);
    }

    public function test_production_environment_rejects_localhost_without_verified_domain(): void
    {
        $this->app['env'] = 'production';
        config(['widget.allow_local_origins' => false]);

        ['key' => $key, 'domain' => $domain] = $this->createWidgetReadyTenant();
        $domain->delete();

        $this->postJson('/widget/v1/session', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertForbidden();
    }
}
