<?php

namespace Tests\Feature;

use App\Enums\Tenancy\TenantStatus;
use App\Enums\Widget\WidgetKeyStatus;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WidgetGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_start_requires_valid_widget_key(): void
    {
        $this->postJson('/widget/v1/session', [
            'widget_key' => 'wk_invalid',
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertForbidden();
    }

    public function test_session_start_succeeds_with_verified_domain(): void
    {
        ['key' => $key] = $this->createWidgetReadyTenant();

        $response = $this->postJson('/widget/v1/session', [
            'widget_key' => $key->public_key,
            'source_url' => 'http://127.0.0.1:8000/widget-demo/static.html',
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['session_token', 'conversation_uuid', 'welcome_message']);

        $this->assertDatabaseHas('conversations', [
            'uuid' => $response->json('conversation_uuid'),
        ]);
    }

    public function test_suspended_tenant_cannot_start_widget_session(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'key' => $key] = $this->createWidgetReadyTenant();

        $tenant->update([
            'status' => TenantStatus::Suspended->value,
            'suspended_at' => now(),
        ]);

        $this->postJson('/widget/v1/session', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertForbidden();
    }

    public function test_revoked_widget_key_is_rejected(): void
    {
        ['key' => $key] = $this->createWidgetReadyTenant();
        $key->update(['status' => WidgetKeyStatus::Revoked->value, 'revoked_at' => now()]);

        $this->postJson('/widget/v1/session', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertForbidden();
    }

    public function test_visitor_can_send_message_and_receive_ai_reply(): void
    {
        ['key' => $key] = $this->createWidgetReadyTenant();

        $session = $this->postJson('/widget/v1/session', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertOk();

        $token = $session->json('session_token');

        $this->postJson('/widget/v1/messages', [
            'body' => 'Hello there',
        ], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()
            ->assertJsonPath('reply.role', 'assistant')
            ->assertJsonPath('reply.body', 'AI reply: Hello there');

        $this->assertDatabaseHas('messages', ['body' => 'Hello there']);
    }

    public function test_offline_intake_is_stored(): void
    {
        ['key' => $key] = $this->createWidgetReadyTenant();

        $token = $this->postJson('/widget/v1/session', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->json('session_token');

        $this->postJson('/widget/v1/offline', [
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'message' => 'Please call me back',
        ], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $this->assertDatabaseHas('messages', [
            'body' => 'Please call me back',
            'role' => 'offline_intake',
        ]);
    }

    public function test_widget_gateway_clears_tenant_context_after_request(): void
    {
        ['key' => $key] = $this->createWidgetReadyTenant();

        $this->postJson('/widget/v1/session', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertOk();

        $context = app(TenantContext::class);
        $this->assertFalse($context->hasTenant());
        $this->assertFalse($context->isIsolationEnforced());
    }

    public function test_unverified_domain_is_rejected_without_local_origin(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'key' => $key] = $this->createWidgetReadyTenant();

        config(['widget.allow_local_origins' => false]);

        $this->postJson('/widget/v1/session', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'https://evil.example',
        ])->assertForbidden();
    }
}
