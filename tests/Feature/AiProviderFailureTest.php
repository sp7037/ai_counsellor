<?php

namespace Tests\Feature;

use App\Enums\AI\AiErrorCategory;
use App\Enums\AI\AiRunStatus;
use App\Enums\Conversations\MessageRole;
use App\Models\AiRun;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AiProviderFailureTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('failureTriggerProvider')]
    public function test_provider_failures_persist_failed_run_and_system_fallback(string $trigger, string $category): void
    {
        ['key' => $key] = $this->createWidgetReadyTenant();
        $token = $this->widgetSessionToken($key);

        $response = $this->postJson('/widget/v1/messages', [
            'body' => $trigger,
            'request_id' => (string) str()->uuid(),
        ], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $this->assertSame('system', $response->json('reply.role'));
        $this->assertDatabaseHas('ai_runs', [
            'status' => AiRunStatus::Failed->value,
            'error_category' => $category,
            'message_id' => null,
        ]);
        $this->assertSame(0, Message::query()->where('role', MessageRole::Assistant->value)->count());
    }

    public static function failureTriggerProvider(): array
    {
        return [
            'timeout' => ['trigger timeout', AiErrorCategory::Timeout->value],
            'auth' => ['trigger auth', AiErrorCategory::Auth->value],
            'rate limit' => ['trigger rate limit', AiErrorCategory::RateLimit->value],
            'content policy' => ['trigger content policy', AiErrorCategory::ContentPolicy->value],
            'malformed' => ['trigger malformed', AiErrorCategory::ProviderError->value],
        ];
    }

    public function test_missing_tenant_key_in_required_mode_fails_safely(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'key' => $key] = $this->createWidgetReadyTenant();
        $this->configureTenantAi($tenant, $user, [
            'credential_mode' => 'tenant_key_required',
            'api_key' => '',
        ]);

        $token = $this->widgetSessionToken($key);

        $response = $this->postJson('/widget/v1/messages', [
            'body' => 'hello',
        ], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $this->assertSame('system', $response->json('reply.role'));
        $this->assertDatabaseHas('ai_runs', [
            'status' => AiRunStatus::Failed->value,
            'error_category' => AiErrorCategory::MissingKey->value,
        ]);
    }

    public function test_platform_managed_mode_uses_platform_credential_source(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'key' => $key] = $this->createWidgetReadyTenant();
        $this->configureTenantAi($tenant, $user, [
            'credential_mode' => 'platform_managed',
        ]);

        $token = $this->widgetSessionToken($key);
        $this->postJson('/widget/v1/messages', ['body' => 'hello'], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $this->assertDatabaseHas('ai_runs', [
            'credential_source' => 'platform',
            'status' => AiRunStatus::Success->value,
        ]);
    }

    public function test_explicit_platform_fallback_uses_tenant_key_when_present(): void
    {
        ['tenant' => $tenant, 'user' => $user, 'key' => $key] = $this->createWidgetReadyTenant();
        $this->configureTenantAi($tenant, $user, [
            'credential_mode' => 'tenant_key_with_explicit_platform_fallback',
            'api_key' => 'sk-tenant-explicit-key',
        ]);

        $token = $this->widgetSessionToken($key);
        $this->postJson('/widget/v1/messages', ['body' => 'hello'], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $this->assertDatabaseHas('ai_runs', [
            'credential_source' => 'tenant',
            'status' => AiRunStatus::Success->value,
        ]);
    }

    public function test_failed_run_does_not_count_as_successful_ai_output_tokens(): void
    {
        ['key' => $key] = $this->createWidgetReadyTenant();
        $token = $this->widgetSessionToken($key);

        $this->postJson('/widget/v1/messages', ['body' => 'trigger timeout'], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $run = AiRun::query()->firstOrFail();
        $this->assertSame(AiRunStatus::Failed->value, $run->status);
        $this->assertNull($run->output_tokens);
        $this->assertNull($run->message_id);
    }

    public function test_contact_capture_with_ai_failure_returns_saved_details_message(): void
    {
        ['tenant' => $tenant, 'key' => $key] = $this->createWidgetReadyTenant();
        $token = $this->widgetSessionToken($key);

        $response = $this->postJson('/widget/v1/messages', [
            'body' => 'trigger timeout My name is Rahul Sharma and my mobile number is 9876543210',
            'request_id' => (string) str()->uuid(),
        ], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $this->assertSame('assistant', $response->json('reply.role'));
        $this->assertStringContainsString('saved your details', (string) $response->json('reply.body'));
        $this->assertStringNotContainsString('temporarily unavailable', (string) $response->json('reply.body'));

        $lead = \App\Models\Lead::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($lead);
        $this->assertSame('Rahul Sharma', $lead->full_name);
        $this->assertSame('9876543210', $lead->mobile);
    }
}
