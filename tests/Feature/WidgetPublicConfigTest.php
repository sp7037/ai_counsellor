<?php

namespace Tests\Feature;

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
        $this->assertArrayNotHasKey('tenant_id', $config);
        $this->assertArrayNotHasKey('id', $config['branding'] ?? []);
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
