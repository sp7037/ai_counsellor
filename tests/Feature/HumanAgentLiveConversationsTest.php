<?php

namespace Tests\Feature;

use App\Enums\Conversations\ConversationMode;
use App\Enums\Tenancy\TenantRole;
use App\Models\AiRun;
use App\Models\Conversation;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Conversations\ConversationHandoffService;
use App\Services\Conversations\ConversationMessageService;
use App\Services\Leads\LeadCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HumanAgentLiveConversationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_super_admin_denied_conversation_workspace(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();
        ['tenant' => $tenant] = $this->createTenantWithMember();

        $this->actingAs($admin)->get(route('workspace.conversations.index', $tenant))->assertForbidden();
    }

    public function test_counsellor_can_access_conversation_inbox(): void
    {
        ['tenant' => $tenant, 'user' => $counsellor] = $this->createTenantWithMember(role: TenantRole::Staff);

        $this->actingAs($counsellor)->get(route('workspace.conversations.index', $tenant))->assertOk();
    }

    public function test_handoff_request_is_idempotent(): void
    {
        $result = $this->createWidgetReadyTenant();
        $token = $this->widgetSessionToken($result['key']);
        $uuid = (string) Str::uuid();

        $first = $this->postJson('/widget/v1/handoff', [
            'handoff_request_uuid' => $uuid,
        ], $this->widgetHeaders($token))->assertOk();

        $second = $this->postJson('/widget/v1/handoff', [
            'handoff_request_uuid' => $uuid,
        ], $this->widgetHeaders($token))->assertOk();

        $this->assertSame($first->json('mode'), $second->json('mode'));
        $this->assertSame(1, Conversation::withoutGlobalScopes()->where('mode', ConversationMode::HandoffRequested->value)->count());
    }

    public function test_no_ai_run_during_human_mode_visitor_message(): void
    {
        $widget = $this->createWidgetReadyTenant();
        $tenant = $widget['tenant'];
        $counsellor = User::factory()->create();
        TenantMembership::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $counsellor->id,
            'role' => TenantRole::Staff->value,
            'status' => 'active',
        ]);

        $token = $this->widgetSessionToken($widget['key']);
        $conversation = Conversation::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->postJson('/widget/v1/handoff', ['handoff_request_uuid' => (string) Str::uuid()], $this->widgetHeaders($token))->assertOk();
        app(ConversationHandoffService::class)->claim($conversation->fresh(), $counsellor);

        Http::fake();
        $runsBefore = AiRun::count();

        $this->postJson('/widget/v1/messages', [
            'body' => 'Hello counsellor',
            'request_id' => (string) Str::uuid(),
        ], $this->widgetHeaders($token))->assertOk();

        $this->assertSame($runsBefore, AiRun::count());
    }

    public function test_counsellor_message_visible_in_widget_poll(): void
    {
        $widget = $this->createWidgetReadyTenant();
        $tenant = $widget['tenant'];
        $counsellor = User::factory()->create();
        TenantMembership::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $counsellor->id,
            'role' => TenantRole::Staff->value,
            'status' => 'active',
        ]);

        $token = $this->widgetSessionToken($widget['key']);
        $conversation = Conversation::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->postJson('/widget/v1/handoff', ['handoff_request_uuid' => (string) Str::uuid()], $this->widgetHeaders($token));
        app(ConversationHandoffService::class)->claim($conversation->fresh(), $counsellor);

        $message = app(ConversationMessageService::class)->sendCounsellorMessage(
            $conversation->fresh(),
            $counsellor,
            'Hello from counsellor',
            (string) Str::uuid(),
        );

        $poll = $this->getJson('/widget/v1/messages/poll', $this->widgetHeaders($token))->assertOk();

        $this->assertTrue(collect($poll->json('messages'))->contains('uuid', $message->uuid));
        $poll->assertJsonMissing(['notes', 'audit', 'prompt']);
    }

    public function test_counsellor_cannot_claim_foreign_tenant_conversation(): void
    {
        $widgetA = $this->createWidgetReadyTenant();
        $tokenA = $this->widgetSessionToken($widgetA['key']);
        $conversation = Conversation::withoutGlobalScopes()->where('tenant_id', $widgetA['tenant']->id)->firstOrFail();
        ['user' => $counsellorB] = $this->createTenantWithMember(role: TenantRole::Staff);

        $this->postJson('/widget/v1/handoff', ['handoff_request_uuid' => (string) Str::uuid()], $this->widgetHeaders($tokenA));

        $this->expectException(ValidationException::class);
        app(ConversationHandoffService::class)->claim(
            Conversation::withoutGlobalScopes()->findOrFail($conversation->id),
            $counsellorB,
        );
    }

    public function test_conversation_to_lead_conversion_is_idempotent(): void
    {
        $widget = $this->createWidgetReadyTenant();
        $this->widgetSessionToken($widget['key']);
        $admin = $widget['user'];
        $conversation = Conversation::withoutGlobalScopes()->where('tenant_id', $widget['tenant']->id)->firstOrFail();

        $this->withTenantContext($admin, $widget['tenant']);

        $lead = app(LeadCreationService::class)->fromConversation($conversation, [
            'full_name' => 'Test Visitor',
            'mobile' => '9876543210',
        ], $admin);

        $this->expectException(ValidationException::class);
        app(LeadCreationService::class)->fromConversation($conversation->fresh(), [
            'full_name' => 'Another',
        ], $admin);

        $this->assertSame($lead->id, $conversation->fresh()->lead_id);
    }

    public function test_tenant_admin_conversation_supervision_route(): void
    {
        ['tenant' => $tenant, 'user' => $admin] = $this->createTenantWithMember(role: TenantRole::Admin);

        $this->actingAs($admin)->get(route('tenant.conversations.index', $tenant))->assertOk();
    }

    public function test_tenant_conversations_index_renders_when_visitor_has_no_display_name_column(): void
    {
        $setup = $this->createWidgetReadyTenant();
        $token = $this->widgetSessionToken($setup['key']);

        $this->postJson('/widget/v1/messages', [
            'body' => 'hello',
            'request_id' => (string) Str::uuid(),
        ], $this->widgetHeaders($token))->assertOk();

        $this->actingAs($setup['user'])
            ->get(route('tenant.conversations.index', $setup['tenant']))
            ->assertOk();
    }

    /**
     * @return array<string, string>
     */
    private function widgetHeaders(string $token): array
    {
        return [
            'Authorization' => 'Bearer '.$token,
            'Origin' => 'http://127.0.0.1:8000',
        ];
    }
}
