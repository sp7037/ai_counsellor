<?php

namespace Tests\Feature;

use App\Enums\Audit\AuditAction;
use App\Enums\Tenancy\TenantRole;
use App\Models\AiRun;
use App\Models\AuditLog;
use App\Models\TenantAiConfig;
use App\Services\AI\SafeAiExceptionMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AiSecretLeakageTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_ai_secret_is_hidden_from_model_serialization(): void
    {
        ['tenant' => $tenant, 'user' => $owner] = $this->createTenantWithMember(role: TenantRole::Owner);
        $this->configureTenantAi($tenant, $owner, [
            'credential_mode' => 'tenant_key_required',
            'api_key' => 'sk-test-secret-1234',
        ]);

        $config = TenantAiConfig::query()->firstOrFail();
        $serialized = json_encode($config->toArray());

        $this->assertStringNotContainsString('sk-test-secret-1234', (string) $serialized);
        $this->assertArrayNotHasKey('encrypted_api_key', $config->toArray());
    }

    public function test_ai_configuration_ui_and_audit_never_store_raw_secret(): void
    {
        ['tenant' => $tenant, 'user' => $owner] = $this->createTenantWithMember(role: TenantRole::Owner);
        $this->actingAs($owner);
        $this->withTenantContext($owner, $tenant);

        Volt::test('tenant.ai.configuration', ['tenant' => $tenant])
            ->set('provider', 'fake')
            ->set('model', 'fake-model')
            ->set('temperature', 0.2)
            ->set('maxOutputTokens', 400)
            ->set('timeoutSeconds', 15)
            ->set('enabled', true)
            ->set('credentialMode', 'tenant_key_required')
            ->set('replaceSecret', true)
            ->set('apiKey', 'sk-ui-secret-9999')
            ->call('save')
            ->assertHasNoErrors();

        $raw = DB::table('tenant_ai_configs')->where('tenant_id', $tenant->id)->value('encrypted_api_key');
        $this->assertNotSame('sk-ui-secret-9999', $raw);

        $this->get(route('tenant.ai.configuration', $tenant))
            ->assertOk()
            ->assertDontSee('sk-ui-secret-9999');

        $audit = AuditLog::query()
            ->where('action', AuditAction::AiSecretReplaced->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $encoded = json_encode($audit->metadata);
        $this->assertStringNotContainsString('sk-ui-secret-9999', (string) $encoded);
        $this->assertStringContainsString('****', (string) $encoded);
    }

    public function test_provider_exception_logging_redacts_api_keys(): void
    {
        Log::spy();

        $mapper = app(SafeAiExceptionMapper::class);
        $mapper->logProviderFailure('openai', new \RuntimeException('Authorization: Bearer sk-log-leak-1234'), 'req-1');

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('ai.provider_failure', \Mockery::on(function (array $context): bool {
                $encoded = json_encode($context);

                return is_string($encoded)
                    && ! str_contains($encoded, 'sk-log-leak-1234')
                    && str_contains($encoded, '[REDACTED]');
            }));
    }

    public function test_ai_run_records_do_not_store_secrets(): void
    {
        ['key' => $key] = $this->createWidgetReadyTenant();
        $token = $this->widgetSessionToken($key);

        $this->postJson('/widget/v1/messages', ['body' => 'hello'], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $run = AiRun::query()->firstOrFail();
        $encoded = json_encode($run->toArray());

        $this->assertStringNotContainsString('sk-', (string) $encoded);
        $this->assertArrayNotHasKey('encrypted_api_key', $run->toArray());
    }

    public function test_widget_api_response_does_not_include_secrets(): void
    {
        ['key' => $key] = $this->createWidgetReadyTenant();
        $token = $this->widgetSessionToken($key);

        $response = $this->postJson('/widget/v1/messages', ['body' => 'hello'], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $encoded = json_encode($response->json());
        $this->assertStringNotContainsString('sk-', (string) $encoded);
        $this->assertStringNotContainsString('encrypted_api_key', (string) $encoded);
    }
}
