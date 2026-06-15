<?php

namespace Tests\Feature;

use App\Enums\AI\AiRunStatus;
use App\Enums\Conversations\MessageRole;
use App\Models\AiRun;
use App\Models\Message;
use App\Services\AI\AiIdempotencyCoordinator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_omitted_client_request_id_still_creates_single_successful_reply(): void
    {
        ['key' => $key] = $this->createWidgetReadyTenant();
        $token = $this->widgetSessionToken($key);

        $response = $this->postJson('/widget/v1/messages', [
            'body' => 'Hello without request id',
        ], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $visitorUuid = $response->json('visitor_message.uuid');
        $this->assertNotEmpty($visitorUuid);
        $this->assertDatabaseHas('messages', [
            'uuid' => $visitorUuid,
            'role' => MessageRole::Visitor->value,
        ]);
        $this->assertDatabaseHas('ai_runs', [
            'request_uuid' => $visitorUuid,
            'status' => AiRunStatus::Success->value,
        ]);
        $this->assertSame(1, Message::query()->where('role', MessageRole::Assistant->value)->count());
    }

    public function test_repeated_identical_request_id_returns_existing_reply(): void
    {
        ['key' => $key] = $this->createWidgetReadyTenant();
        $token = $this->widgetSessionToken($key);
        $requestId = (string) str()->uuid();

        $first = $this->postJson('/widget/v1/messages', [
            'body' => 'Repeat me',
            'request_id' => $requestId,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $second = $this->postJson('/widget/v1/messages', [
            'body' => 'Repeat me',
            'request_id' => $requestId,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $this->assertSame($first->json('reply.uuid'), $second->json('reply.uuid'));
        $this->assertSame(1, Message::query()->where('role', MessageRole::Visitor->value)->count());
        $this->assertSame(1, Message::query()->where('role', MessageRole::Assistant->value)->count());
        $this->assertSame(1, AiRun::query()->where('status', AiRunStatus::Success->value)->count());
    }

    public function test_retry_after_provider_timeout_creates_one_assistant_message(): void
    {
        ['key' => $key] = $this->createWidgetReadyTenant();
        $token = $this->widgetSessionToken($key);
        $requestId = (string) str()->uuid();

        $failed = $this->postJson('/widget/v1/messages', [
            'body' => 'trigger fail-once',
            'request_id' => $requestId,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $this->assertSame('system', $failed->json('reply.role'));
        $this->assertDatabaseHas('ai_runs', [
            'request_uuid' => $requestId,
            'status' => AiRunStatus::Failed->value,
        ]);

        $success = $this->postJson('/widget/v1/messages', [
            'body' => 'trigger fail-once',
            'request_id' => $requestId,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $this->assertSame('assistant', $success->json('reply.role'));
        $this->assertSame(1, Message::query()->where('role', MessageRole::Visitor->value)->count());
        $this->assertSame(1, Message::query()->where('role', MessageRole::Assistant->value)->count());
        $this->assertSame(1, AiRun::query()->where('status', AiRunStatus::Success->value)->count());
    }

    public function test_cross_tenant_identical_request_uuid_remains_isolated(): void
    {
        ['key' => $keyA] = $this->createWidgetReadyTenant();
        ['key' => $keyB] = $this->createWidgetReadyTenant();
        $sharedRequestId = (string) str()->uuid();

        $tokenA = $this->widgetSessionToken($keyA);
        $tokenB = $this->widgetSessionToken($keyB);

        $this->postJson('/widget/v1/messages', [
            'body' => 'Tenant A message',
            'request_id' => $sharedRequestId,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$tokenA,
        ])->assertOk();

        $this->postJson('/widget/v1/messages', [
            'body' => 'Tenant B message',
            'request_id' => $sharedRequestId,
        ], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$tokenB,
        ])->assertOk();

        $this->assertSame(2, AiRun::withoutGlobalScopes()->where('request_uuid', $sharedRequestId)->count());
        $this->assertSame(2, Message::withoutGlobalScopes()->where('role', MessageRole::Assistant->value)->count());
    }

    public function test_finalize_success_prevents_duplicate_assistant_for_same_triggering_message(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'key' => $key] = $this->createWidgetReadyTenant();
        $token = $this->widgetSessionToken($key);

        $this->postJson('/widget/v1/messages', ['body' => 'once'], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $run = AiRun::query()->where('status', AiRunStatus::Success->value)->firstOrFail();
        $coordinator = app(AiIdempotencyCoordinator::class);

        $first = $coordinator->finalizeSuccess($run, 'Duplicate guard content');
        $second = $coordinator->finalizeSuccess($run->fresh(), 'Duplicate guard content');

        $this->assertSame($first['assistant']->id, $second['assistant']->id);
        $this->assertSame(1, Message::query()->where('role', MessageRole::Assistant->value)->count());
    }
}
