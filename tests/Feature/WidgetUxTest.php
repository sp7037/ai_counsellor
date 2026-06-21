<?php

namespace Tests\Feature;

use App\Models\TenantSettings;
use App\Services\Configuration\WidgetPublicConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WidgetUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_configuration_includes_handoff_ux_settings(): void
    {
        ['key' => $key] = $this->createWidgetReadyTenant();

        $config = $this->postJson('/widget/v1/session', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertOk()->json('configuration');

        $this->assertArrayHasKey('human_transfer', $config);
        $this->assertArrayHasKey('promote_after_messages', $config['human_transfer']);
        $this->assertArrayHasKey('subtle_label', $config['human_transfer']);
        $this->assertArrayHasKey('offer_message', $config['human_transfer']);
        $this->assertArrayHasKey('logo_url', $config['branding']);
        $this->assertArrayHasKey('powered_by', $config);
        $this->assertArrayHasKey('label', $config['powered_by']);
        $this->assertSame(3, $config['human_transfer']['promote_after_messages']);
    }

    public function test_widget_session_includes_handoff_ux_configuration(): void
    {
        ['key' => $key] = $this->createWidgetReadyTenant();

        $response = $this->postJson('/widget/v1/session', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertOk();

        $handoff = $response->json('configuration.human_transfer');
        $this->assertIsArray($handoff);
        $this->assertArrayHasKey('subtle_label', $handoff);
        $this->assertArrayHasKey('promote_after_messages', $handoff);
        $this->assertSame(3, (int) $handoff['promote_after_messages']);
    }

    public function test_new_tenant_default_welcome_encourages_ai_first(): void
    {
        ['key' => $key] = $this->createWidgetReadyTenant();

        $welcome = $this->postJson('/widget/v1/session', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertOk()->json('welcome_message');

        $this->assertStringContainsString('AI counsellor', (string) $welcome);
    }

    public function test_expired_session_returns_safe_401_for_recovery_flow(): void
    {
        ['key' => $key] = $this->createWidgetReadyTenant();

        $token = $this->postJson('/widget/v1/session', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertOk()->json('session_token');

        $this->travel(3)->hours();

        $this->postJson('/widget/v1/messages', ['body' => 'hello'], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid or expired session.');
    }

    public function test_start_new_chat_after_expiry_creates_fresh_session(): void
    {
        ['key' => $key] = $this->createWidgetReadyTenant();

        $expiredToken = $this->postJson('/widget/v1/session', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertOk()->json('session_token');

        $this->travel(3)->hours();

        $this->postJson('/widget/v1/messages', ['body' => 'stale'], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$expiredToken,
        ])->assertUnauthorized();

        $newSession = $this->postJson('/widget/v1/session', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertOk();

        $newToken = $newSession->json('session_token');
        $this->assertNotSame($expiredToken, $newToken);

        $this->postJson('/widget/v1/messages', ['body' => 'fresh start'], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$newToken,
        ])->assertOk()->assertJsonPath('reply.role', 'assistant');
    }

    public function test_third_message_in_same_session_still_succeeds(): void
    {
        ['key' => $key] = $this->createWidgetReadyTenant();

        $token = $this->postJson('/widget/v1/session', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertOk()->json('session_token');

        $headers = [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ];

        foreach (['One', 'Two', 'Three'] as $message) {
            $this->postJson('/widget/v1/messages', ['body' => $message], $headers)
                ->assertOk()
                ->assertJsonStructure(['visitor_message', 'reply', 'session_expires_at']);
        }
    }

    public function test_built_widget_bundle_includes_ux_recovery_and_handoff_markers(): void
    {
        $path = public_path('build/widget.js');
        $this->assertFileExists($path);

        $js = (string) file_get_contents($path);

        $this->assertStringContainsString('previous chat session expired', $js);
        $this->assertStringContainsString('Start new chat', $js);
        $this->assertStringContainsString('Need human help?', $js);
        $this->assertStringContainsString('is typing', $js);
        $this->assertStringContainsString('ac-widget-panel.expanded', $js);
        $this->assertStringContainsString('ac-widget-avatar-fallback', $js);
        $this->assertStringContainsString('ac-widget-powered-by-label', $js);
        $this->assertStringContainsString('promote_after_messages', $js);
        $this->assertStringContainsString('handoffProminent:!1', $js);
        $this->assertStringContainsString('Need human help?', $js);
        $this->assertStringNotContainsString('sk-', $js);
    }

    public function test_built_widget_bundle_suppresses_handoff_after_human_takeover(): void
    {
        $path = public_path('build/widget.js');
        $this->assertFileExists($path);

        $js = (string) file_get_contents($path);

        // Human takeover state + status markers (CTA suppression logic).
        $this->assertStringContainsString('ac-widget-handoff-status', $js);
        $this->assertStringContainsString('Waiting for counsellor', $js);
        $this->assertStringContainsString('You are chatting with', $js);
        $this->assertStringContainsString('Human counsellor is assisting you now', $js);
        // Frontend state flags that drive suppression are present in the bundle.
        $this->assertStringContainsString('humanMode', $js);
        $this->assertStringContainsString('handoffRequested', $js);
    }

    public function test_built_widget_bundle_includes_platform_launcher_logo_handling(): void
    {
        $path = public_path('build/widget.js');
        $this->assertFileExists($path);

        $js = (string) file_get_contents($path);

        // Floating launcher consumes the platform launcher logo with image fallbacks.
        $this->assertStringContainsString('ac-widget-toggle-logo', $js);
        $this->assertStringContainsString('launcher', $js);
    }

    public function test_built_widget_bundle_renders_launcher_logo_on_first_load(): void
    {
        $path = public_path('build/widget.js');
        $this->assertFileExists($path);

        $js = (string) file_get_contents($path);

        // Launcher branding is fetched on boot (no widget open required)...
        $this->assertStringContainsString('/bootstrap', $js);
        // ...and rendered in a high-contrast white badge.
        $this->assertStringContainsString('ac-widget-toggle-badge', $js);
        // A loading placeholder avoids the "AI" text flash while the logo loads.
        $this->assertStringContainsString('ac-loading', $js);
    }

    public function test_built_widget_bundle_includes_launcher_teaser_label(): void
    {
        $path = public_path('build/widget.js');
        $this->assertFileExists($path);

        $js = (string) file_get_contents($path);

        $this->assertStringContainsString('ac-widget-teaser', $js);
        $this->assertStringContainsString('Ask AI Counsellor', $js);
    }

    public function test_config_endpoint_exposes_conversation_mode_for_human_detection(): void
    {
        ['key' => $key] = $this->createWidgetReadyTenant();

        $token = $this->postJson('/widget/v1/session', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertOk()->json('session_token');

        $config = $this->getJson('/widget/v1/config', [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        // The widget derives human mode from this field after a refresh/restore.
        $config->assertJsonPath('mode', 'ai');
        $this->assertIsArray($config->json('messages'));
    }

    public function test_configuration_does_not_expose_provider_secrets(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember();
        $this->withTenantContext($user, $tenant);

        $settings = TenantSettings::query()->firstOrNew(['tenant_id' => $tenant->id]);
        $settings->tenant()->associate($tenant);
        $settings->display_name = $tenant->name;
        $settings->assistant_name = 'Admissions Guide';
        $settings->save();

        $config = app(WidgetPublicConfigService::class)->forTenant($tenant);
        $encoded = json_encode($config);

        $this->assertStringNotContainsString('api_key', (string) $encoded);
        $this->assertStringNotContainsString('OPENAI', (string) $encoded);
        $this->assertSame('Admissions Guide', $config['branding']['assistant_name']);
    }
}
