<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Services\AI\AiPromptBuilder;
use App\Services\AI\ConversationContextBuilder;
use App\Services\AI\CounsellingFlowHelper;
use App\Services\Widget\HandoffPromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CounsellorIntelligencePolishTest extends TestCase
{
    use RefreshDatabase;

    public function test_handoff_promotion_service_detects_high_risk_topics(): void
    {
        ['tenant' => $tenant, 'key' => $key] = $this->createWidgetReadyTenant();

        $this->postJson('/widget/v1/session', ['widget_key' => $key->public_key], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertOk();

        $conversation = \App\Models\Conversation::query()->firstOrFail();
        $service = app(HandoffPromotionService::class);

        $result = $service->evaluate(
            $conversation,
            'I need urgent payment admission help and exact fee commitment',
            null,
            [],
        );

        $this->assertTrue($result['prominent']);
        $this->assertSame('high_risk', $result['reason']);
    }

    public function test_counselling_flow_skips_already_asked_fields(): void
    {
        ['key' => $key] = $this->createWidgetReadyTenant();

        $token = $this->postJson('/widget/v1/session', ['widget_key' => $key->public_key], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->json('session_token');

        $headers = [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ];

        $this->postJson('/widget/v1/messages', [
            'body' => 'Can you guide me for MBBS abroad?',
            'request_id' => (string) str()->uuid(),
        ], $headers)->assertOk();

        $conversation = \App\Models\Conversation::query()->with('lead')->firstOrFail();
        $context = app(ConversationContextBuilder::class)->build($conversation);
        $helper = app(CounsellingFlowHelper::class);

        $afterFirst = $helper->assess($conversation, 'Can you guide me for MBBS abroad?', $context);
        $this->assertSame('approximate budget', $afterFirst['next_field']);

        $lead = $conversation->lead;
        $this->assertNotNull($lead);
        $this->assertContains('neet_status', (array) ($lead->metadata['counselling_asked_fields'] ?? []));

        $helper->recordAskedField($lead, 'budget');
        $context = app(ConversationContextBuilder::class)->build($conversation->fresh());
        $afterBudgetAsked = $helper->assess($conversation->fresh(), 'Still exploring options', $context);

        $this->assertSame('preferred country', $afterBudgetAsked['next_field']);
    }

    public function test_prompt_builder_includes_knowledge_grounding_rules(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'key' => $key] = $this->createWidgetReadyTenant();

        $this->postJson('/widget/v1/session', ['widget_key' => $key->public_key], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertOk();

        $conversation = \App\Models\Conversation::query()->firstOrFail();
        $settings = \App\Models\TenantSettings::query()->where('tenant_id', $tenant->id)->first();

        $messages = app(AiPromptBuilder::class)->build(
            $tenant,
            $settings,
            $conversation,
            'What are the exact fees?',
            [],
        );

        $joined = implode("\n", array_map(fn ($message) => $message->content, $messages));
        $this->assertStringContainsString('published knowledge', strtolower($joined));
        $this->assertStringContainsString('Do not invent', $joined);
    }

    public function test_prompt_builder_includes_concise_counselling_style_for_mbbs_flow(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'key' => $key] = $this->createWidgetReadyTenant();

        $this->postJson('/widget/v1/session', ['widget_key' => $key->public_key], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertOk();

        $conversation = \App\Models\Conversation::query()->firstOrFail();
        $settings = \App\Models\TenantSettings::query()->where('tenant_id', $tenant->id)->first();

        $messages = app(AiPromptBuilder::class)->build(
            $tenant,
            $settings,
            $conversation,
            'Can you guide me for MBBS abroad?',
            [],
        );

        $joined = implode("\n", array_map(fn ($message) => $message->content, $messages));

        $this->assertStringContainsString('Counselling flow (MBBS abroad enquiry detected)', $joined);
        $this->assertStringContainsString('Maximum 120 words and maximum 4 bullet points', $joined);
        $this->assertStringContainsString('Avoid long country lists unless the visitor explicitly asks', $joined);
    }

    public function test_existing_lead_contact_is_not_overwritten_by_extraction(): void
    {
        ['tenant' => $tenant, 'key' => $key] = $this->createWidgetReadyTenant();

        $token = $this->postJson('/widget/v1/session', ['widget_key' => $key->public_key], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->json('session_token');

        $headers = [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ];

        $this->postJson('/widget/v1/messages', [
            'body' => 'My name is Rahul Kumar, mobile 9876543210, MBBS abroad.',
            'request_id' => (string) str()->uuid(),
        ], $headers)->assertOk();

        $lead = Lead::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $lead->update(['full_name' => 'Rahul Kumar Official', 'mobile' => '9999900000']);

        $this->postJson('/widget/v1/messages', [
            'body' => 'My name is Other Person, mobile 9111111111',
            'request_id' => (string) str()->uuid(),
        ], $headers)->assertOk();

        $lead->refresh();
        $this->assertSame('Rahul Kumar Official', $lead->full_name);
        $this->assertSame('9999900000', $lead->mobile);
    }
}
