<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Services\AI\AiReplyCompletionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiReplyCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_completion_guard_detects_mid_sentence_truncation(): void
    {
        $guard = app(AiReplyCompletionGuard::class);

        $this->assertTrue($guard->looksIncomplete('Ensure the university is NMC-recognised; otherwise,'));
        $this->assertTrue($guard->shouldRetryForTruncation('Short guidance here', 'length'));
    }

    public function test_completion_guard_repairs_truncated_reply_with_follow_up(): void
    {
        $guard = app(AiReplyCompletionGuard::class);

        $repaired = $guard->finalize(
            'Ensure the university is NMC-recognised; otherwise,',
            'length',
            'Which intake are you targeting?',
        );

        $this->assertStringEndsWith('?', $repaired);
        $this->assertStringContainsString('Which intake are you targeting?', $repaired);
        $this->assertFalse($guard->looksIncomplete($repaired));
    }

    public function test_counselling_reply_does_not_exceed_reasonable_length(): void
    {
        ['key' => $key] = $this->createWidgetReadyTenant();

        $token = $this->postJson('/widget/v1/session', ['widget_key' => $key->public_key], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->json('session_token');

        $headers = [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ];

        $response = $this->postJson('/widget/v1/messages', [
            'body' => 'Can you guide me for MBBS abroad?',
            'request_id' => (string) str()->uuid(),
        ], $headers)->assertOk();

        $body = (string) $response->json('reply.body');
        $guard = app(AiReplyCompletionGuard::class);

        $this->assertLessThanOrEqual(
            (int) config('ai.counselling_max_words', 120),
            $guard->wordCount($body),
        );
    }

    public function test_counselling_reply_has_no_more_than_four_bullets(): void
    {
        ['key' => $key] = $this->createWidgetReadyTenant();

        $token = $this->postJson('/widget/v1/session', ['widget_key' => $key->public_key], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->json('session_token');

        $headers = [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ];

        $response = $this->postJson('/widget/v1/messages', [
            'body' => 'Can you guide me for MBBS abroad?',
            'request_id' => (string) str()->uuid(),
        ], $headers)->assertOk();

        $body = (string) $response->json('reply.body');
        $guard = app(AiReplyCompletionGuard::class);

        $this->assertLessThanOrEqual(
            (int) config('ai.counselling_max_bullets', 4),
            $guard->bulletCount($body),
        );
    }

    public function test_prompt_builder_includes_concise_counselling_response_rules(): void
    {
        ['tenant' => $tenant, 'key' => $key] = $this->createWidgetReadyTenant();

        $this->postJson('/widget/v1/session', ['widget_key' => $key->public_key], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->assertOk();

        $conversation = \App\Models\Conversation::query()->firstOrFail();
        $settings = \App\Models\TenantSettings::query()->where('tenant_id', $tenant->id)->first();

        $messages = app(\App\Services\AI\AiPromptBuilder::class)->build(
            $tenant,
            $settings,
            $conversation,
            'Can you guide me for MBBS abroad?',
            [],
        );

        $joined = implode("\n", array_map(fn ($message) => $message->content, $messages));

        $this->assertStringContainsString('Widget counselling response style', $joined);
        $this->assertStringContainsString('Maximum 120 words and maximum 4 bullet points', $joined);
        $this->assertStringContainsString('exactly one complete follow-up question', strtolower($joined));
        $this->assertStringContainsString('Do not repeat fields already collected', $joined);
    }

    public function test_counselling_reply_ends_with_complete_sentence_or_question(): void
    {
        ['key' => $key] = $this->createWidgetReadyTenant();

        $token = $this->postJson('/widget/v1/session', ['widget_key' => $key->public_key], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->json('session_token');

        $headers = [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ];

        $response = $this->postJson('/widget/v1/messages', [
            'body' => 'Can you guide me for MBBS abroad?',
            'request_id' => (string) str()->uuid(),
        ], $headers)->assertOk();

        $body = (string) $response->json('reply.body');
        $guard = app(AiReplyCompletionGuard::class);

        $this->assertFalse($guard->looksIncomplete($body));
        $this->assertMatchesRegularExpression('/[.!?][\'")\]]*\s*$/u', $body);
    }

    public function test_finish_reason_length_is_repaired_before_message_is_saved(): void
    {
        ['tenant' => $tenant, 'key' => $key] = $this->createWidgetReadyTenant();

        $token = $this->postJson('/widget/v1/session', ['widget_key' => $key->public_key], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->json('session_token');

        $headers = [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ];

        $response = $this->postJson('/widget/v1/messages', [
            'body' => 'trigger length truncate Can you guide me for MBBS abroad?',
            'request_id' => (string) str()->uuid(),
        ], $headers)->assertOk();

        $body = (string) $response->json('reply.body');
        $guard = app(AiReplyCompletionGuard::class);

        $this->assertFalse($guard->looksIncomplete($body));
        $this->assertStringNotContainsString('otherwise,', $body);

        $message = Message::query()->where('tenant_id', $tenant->id)->where('role', 'assistant')->latest('id')->first();
        $this->assertNotNull($message);
        $this->assertSame($body, $message->body);
    }

    public function test_finish_reason_length_retries_once_before_repair(): void
    {
        ['tenant' => $tenant, 'key' => $key] = $this->createWidgetReadyTenant();

        $token = $this->postJson('/widget/v1/session', ['widget_key' => $key->public_key], [
            'Origin' => 'http://127.0.0.1:8000',
        ])->json('session_token');

        $headers = [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ];

        $response = $this->postJson('/widget/v1/messages', [
            'body' => 'trigger length truncate retry I am targeting 2026 intake for MBBS abroad',
            'request_id' => (string) str()->uuid(),
        ], $headers)->assertOk();

        $body = (string) $response->json('reply.body');

        $this->assertStringContainsString('mobile number', strtolower($body));
        $this->assertStringNotContainsString('otherwise,', $body);

        $message = Message::query()->where('tenant_id', $tenant->id)->where('role', 'assistant')->latest('id')->first();
        $this->assertNotNull($message);
        $this->assertSame($body, $message->body);
    }
}
