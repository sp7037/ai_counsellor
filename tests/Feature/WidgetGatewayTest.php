<?php

namespace Tests\Feature;

use App\Enums\Tenancy\TenantRole;
use App\Enums\Tenancy\TenantStatus;
use App\Enums\Widget\WidgetKeyStatus;
use App\Services\Tenancy\TenantContext;
use App\Services\Widget\TenantDomainService;
use App\Services\Widget\WidgetKeyService;
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

    public function test_multiple_messages_in_same_session_succeed(): void
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

        $this->postJson('/widget/v1/messages', ['body' => 'First message'], $headers)
            ->assertOk()
            ->assertJsonPath('reply.role', 'assistant')
            ->assertJsonStructure(['session_expires_at']);

        $this->travel(30)->seconds();

        $this->postJson('/widget/v1/messages', ['body' => 'Second message'], $headers)
            ->assertOk()
            ->assertJsonPath('reply.role', 'assistant')
            ->assertJsonPath('reply.body', 'AI reply: Second message');
    }

    public function test_invalid_session_token_returns_safe_401(): void
    {
        ['key' => $key] = $this->createWidgetReadyTenant();

        $this->postJson('/widget/v1/session', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertOk();

        $this->postJson('/widget/v1/messages', ['body' => 'hello'], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer wk_totally_invalid_token',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid or expired session.');
    }

    public function test_expired_session_returns_safe_401(): void
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

    public function test_zero_configured_session_ttl_still_allows_follow_up_messages(): void
    {
        config(['widget.session_ttl_minutes' => 0]);

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

        $this->postJson('/widget/v1/messages', ['body' => 'First'], $headers)->assertOk();
        $this->travel(30)->seconds();
        $this->postJson('/widget/v1/messages', ['body' => 'Second'], $headers)
            ->assertOk()
            ->assertJsonPath('reply.body', 'AI reply: Second');
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
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Widget domain is not allowed.')
            ->assertJsonPath('code', 'domain_not_allowed');
    }

    public function test_inactive_widget_key_returns_safe_message(): void
    {
        ['key' => $key] = $this->createWidgetReadyTenant();
        $key->update(['status' => WidgetKeyStatus::Revoked->value, 'revoked_at' => now()]);

        $this->postJson('/widget/v1/session', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Widget key is inactive.')
            ->assertJsonPath('code', 'inactive_widget_key');
    }

    public function test_missing_widget_entitlement_returns_safe_message(): void
    {
        app(TenantContext::class)->clear();

        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(
            withSubscription: false,
            role: TenantRole::Owner,
        );
        $key = app(WidgetKeyService::class)->create($tenant, 'Site widget', $user);
        app(TenantDomainService::class)->add($tenant, 'srworlds.in', $user);

        config(['widget.allow_local_origins' => false]);

        $this->postJson('/widget/v1/session', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'https://srworlds.in',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Widget feature is not enabled for this tenant.')
            ->assertJsonPath('code', 'service_unavailable');
    }

    public function test_active_key_verified_domain_and_entitlement_start_session(): void
    {
        app(TenantContext::class)->clear();

        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Owner);
        $key = app(WidgetKeyService::class)->create($tenant, 'Site widget', $user);
        app(TenantDomainService::class)->add($tenant, 'www.srworlds.in', $user);

        config(['widget.allow_local_origins' => false]);

        $this->postJson('/widget/v1/session', [
            'widget_key' => $key->public_key,
            'source_url' => 'https://srworlds.in/ai-widget-test.html',
        ], [
            'Origin' => 'https://srworlds.in',
        ])
            ->assertOk()
            ->assertJsonStructure(['session_token', 'conversation_uuid'])
            ->assertHeader('Access-Control-Allow-Origin', 'https://srworlds.in');
    }

    public function test_options_preflight_returns_cors_headers_for_verified_domain(): void
    {
        app(TenantContext::class)->clear();

        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Owner);
        $key = app(WidgetKeyService::class)->create($tenant, 'Site widget', $user);
        app(TenantDomainService::class)->add($tenant, 'srworlds.in', $user);

        config(['widget.allow_local_origins' => false]);

        $this->withHeaders([
            'Origin' => 'https://srworlds.in',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'content-type',
        ])->options('/widget/v1/session')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'https://srworlds.in')
            ->assertHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->assertHeader('Vary', 'Origin');
    }

    public function test_options_preflight_rejects_unverified_domain(): void
    {
        ['key' => $key] = $this->createWidgetReadyTenant();

        config(['widget.allow_local_origins' => false]);

        $response = $this->withHeaders([
            'Origin' => 'https://evil.example',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'content-type',
        ])->options('/widget/v1/session');

        $response->assertForbidden();
        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_session_start_returns_cors_headers_for_verified_origin(): void
    {
        app(TenantContext::class)->clear();

        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Owner);
        $key = app(WidgetKeyService::class)->create($tenant, 'Site widget', $user);
        app(TenantDomainService::class)->add($tenant, 'www.srworlds.in', $user);

        config(['widget.allow_local_origins' => false]);

        $this->postJson('/widget/v1/session', [
            'widget_key' => $key->public_key,
        ], [
            'Origin' => 'https://www.srworlds.in',
        ])
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'https://www.srworlds.in')
            ->assertJsonStructure(['session_token', 'conversation_uuid']);
    }

    public function test_messages_options_preflight_returns_cors_headers(): void
    {
        app(TenantContext::class)->clear();

        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Owner);
        $key = app(WidgetKeyService::class)->create($tenant, 'Site widget', $user);
        app(TenantDomainService::class)->add($tenant, 'srworlds.in', $user);

        config(['widget.allow_local_origins' => false]);

        $this->withHeaders([
            'Origin' => 'https://srworlds.in',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'content-type, authorization',
        ])->options('/widget/v1/messages')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'https://srworlds.in');
    }
}
