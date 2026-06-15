<?php

namespace Tests\Feature;

use App\Enums\AI\AiRunStatus;
use App\Models\AiRun;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiUsageIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_run_links_triggering_message_provider_and_usage_fields(): void
    {
        ['key' => $key] = $this->createWidgetReadyTenant();
        $token = $this->widgetSessionToken($key);

        $response = $this->postJson('/widget/v1/messages', [
            'body' => 'usage integrity',
            'request_id' => (string) str()->uuid(),
        ], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $visitor = Message::query()->where('uuid', $response->json('visitor_message.uuid'))->firstOrFail();
        $run = AiRun::query()->firstOrFail();

        $this->assertSame($visitor->tenant_id, $run->tenant_id);
        $this->assertSame($visitor->conversation_id, $run->conversation_id);
        $this->assertSame($visitor->id, $run->triggering_message_id);
        $this->assertSame('fake', $run->provider);
        $this->assertSame('fake-model', $run->model);
        $this->assertSame(AiRunStatus::Success->value, $run->status);
        $this->assertNotNull($run->message_id);
        $this->assertSame(10, $run->input_tokens);
        $this->assertSame(8, $run->output_tokens);
        $this->assertGreaterThan(0, $run->latency_ms);
    }

    public function test_failed_run_cannot_link_to_assistant_message(): void
    {
        ['key' => $key] = $this->createWidgetReadyTenant();
        $token = $this->widgetSessionToken($key);

        $this->postJson('/widget/v1/messages', [
            'body' => 'trigger auth',
        ], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $run = AiRun::query()->firstOrFail();
        $this->assertNull($run->message_id);
        $this->assertNull($run->output_tokens);
    }
}
