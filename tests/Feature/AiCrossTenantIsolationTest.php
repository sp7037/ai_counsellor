<?php

namespace Tests\Feature;

use App\Contracts\Knowledge\KnowledgeRetrievalContract;
use App\Enums\AI\AiCredentialMode;
use App\Enums\Conversations\MessageRole;
use App\Exceptions\AI\AiProviderException;
use App\Models\AiRun;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WidgetSession;
use App\Services\AI\AiConversationOrchestrator;
use App\Services\AI\TenantAiConfigService;
use App\Services\Knowledge\KnowledgeItemService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiCrossTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_a_cannot_reuse_tenant_b_widget_session_token(): void
    {
        ['key' => $keyB] = $this->createWidgetReadyTenant();
        $tokenB = $this->widgetSessionToken($keyB);

        $this->postJson('/widget/v1/messages', ['body' => 'hello'], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$tokenB,
        ])->assertOk();

        app(TenantContext::class)->clear();

        $this->assertSame(1, Conversation::withoutGlobalScopes()->count());
    }

    public function test_tenant_a_widget_cannot_obtain_tenant_b_request_uuid_result(): void
    {
        ['key' => $keyA] = $this->createWidgetReadyTenant();
        ['key' => $keyB] = $this->createWidgetReadyTenant();
        $requestId = (string) str()->uuid();

        $tokenA = $this->widgetSessionToken($keyA);
        $tokenB = $this->widgetSessionToken($keyB);

        $tenantBReply = $this->postJson('/widget/v1/messages', [
            'body' => 'Tenant B only',
            'request_id' => $requestId,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$tokenB,
        ])->assertOk()->json('reply.uuid');

        $tenantAReply = $this->postJson('/widget/v1/messages', [
            'body' => 'Tenant A only',
            'request_id' => $requestId,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$tokenA,
        ])->assertOk()->json('reply.uuid');

        $this->assertNotSame($tenantBReply, $tenantAReply);
    }

    public function test_tenant_a_cannot_use_tenant_b_encrypted_provider_key(): void
    {
        ['tenant' => $tenantA, 'user' => $userA, 'key' => $keyA] = $this->createWidgetReadyTenant();
        ['tenant' => $tenantB, 'user' => $userB] = $this->createWidgetReadyTenant();

        $this->configureTenantAi($tenantB, $userB, [
            'credential_mode' => AiCredentialMode::TenantKeyRequired->value,
            'api_key' => 'sk-tenant-b-secret-key',
        ]);

        $this->configureTenantAi($tenantA, $userA, [
            'credential_mode' => AiCredentialMode::TenantKeyRequired->value,
            'api_key' => 'sk-tenant-a-secret-key',
        ]);

        $tokenA = $this->widgetSessionToken($keyA);

        $this->postJson('/widget/v1/messages', ['body' => 'hello tenant a'], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$tokenA,
        ])->assertOk();

        $run = AiRun::query()->where('tenant_id', $tenantA->id)->firstOrFail();
        $this->assertSame('tenant', $run->credential_source);

        app(TenantContext::class)->clear();
        app(TenantContext::class)->resolveForUser($userB, $tenantB);
        app(TenantContext::class)->enforceIsolation();

        $config = app(TenantAiConfigService::class)->getEffectiveConfig($tenantB);
        $this->assertSame('tenant', $config['credential_source']->value);
        $this->assertSame('sk-tenant-b-secret-key', $config['api_key']);
        $this->assertNotSame('sk-tenant-a-secret-key', $config['api_key']);
    }

    public function test_tenant_b_published_knowledge_is_not_retrieved_for_tenant_a(): void
    {
        ['tenant' => $tenantA, 'user' => $userA, 'key' => $keyA] = $this->createWidgetReadyTenant();
        ['tenant' => $tenantB, 'user' => $userB, 'key' => $keyB] = $this->createWidgetReadyTenant();

        app(TenantContext::class)->resolveForUser($userB, $tenantB);
        app(TenantContext::class)->enforceIsolation();

        $item = app(KnowledgeItemService::class)->createDraft($tenantB, [
            'type' => 'faq',
            'title' => 'Tenant B exclusive fact',
            'body' => 'Only tenant B should see this published fact.',
        ], $userB);
        app(KnowledgeItemService::class)->publish($item, $userB);

        app(TenantContext::class)->clear();

        $results = app(KnowledgeRetrievalContract::class)->searchPublished($tenantA, 'Tenant B exclusive fact', 5);
        $this->assertSame([], $results);

        $tokenA = $this->widgetSessionToken($keyA);
        $response = $this->postJson('/widget/v1/messages', [
            'body' => 'Tell me Tenant B exclusive fact',
        ], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$tokenA,
        ])->assertOk();

        $this->assertSame('assistant', $response->json('reply.role'));
    }

    public function test_orchestrator_rejects_foreign_triggering_message(): void
    {
        ['tenant' => $tenantA, 'key' => $keyA] = $this->createWidgetReadyTenant();
        ['tenant' => $tenantB, 'key' => $keyB] = $this->createWidgetReadyTenant();

        $tokenB = $this->widgetSessionToken($keyB);
        $this->postJson('/widget/v1/messages', ['body' => 'create visitor'], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$tokenB,
        ])->assertOk();

        $foreignMessage = Message::withoutGlobalScopes()
            ->where('tenant_id', $tenantB->id)
            ->where('role', MessageRole::Visitor->value)
            ->firstOrFail();
        $conversationA = Conversation::query()->where('tenant_id', $tenantA->id)->first()
            ?? Conversation::withoutGlobalScopes()->where('tenant_id', $tenantA->id)->first();

        if ($conversationA === null) {
            $tokenA = $this->widgetSessionToken($keyA);
            $this->postJson('/widget/v1/messages', ['body' => 'bootstrap'], [
                'Origin' => 'http://127.0.0.1:8000',
                'Authorization' => 'Bearer '.$tokenA,
            ]);
            $conversationA = Conversation::withoutGlobalScopes()->where('tenant_id', $tenantA->id)->firstOrFail();
        }

        app(TenantContext::class)->setFromWidgetGateway($tenantA);
        app(TenantContext::class)->enforceIsolation();

        $this->expectException(AiProviderException::class);

        app(AiConversationOrchestrator::class)->respond(
            tenant: $tenantA,
            conversation: $conversationA,
            triggeringMessage: $foreignMessage,
            visitorMessage: 'attack',
            knowledge: [],
            requestUuid: (string) str()->uuid(),
        );
    }

    public function test_tenant_context_clears_after_orchestrator_success_and_failure(): void
    {
        ['key' => $key] = $this->createWidgetReadyTenant();
        $token = $this->widgetSessionToken($key);
        $context = app(TenantContext::class);

        $this->postJson('/widget/v1/messages', ['body' => 'trigger timeout'], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $session = WidgetSession::query()->firstOrFail();
        $context->setFromWidgetGateway($session->tenant);
        $context->enforceIsolation();
        $context->clear();

        $this->assertFalse($context->hasTenant());

        $this->postJson('/widget/v1/messages', ['body' => 'success path'], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $context->clear();
        $this->assertFalse($context->hasTenant());
    }
}
