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
            (int) config('ai.max_counselling_reply_words', 180),
            $guard->wordCount($body),
        );
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
