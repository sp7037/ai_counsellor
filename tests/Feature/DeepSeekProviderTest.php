<?php

namespace Tests\Feature;

use App\Data\AI\AiMessage;
use App\Data\AI\AiRequest;
use App\Enums\AI\AiErrorCategory;
use App\Enums\AI\AiRunStatus;
use App\Enums\Conversations\MessageRole;
use App\Enums\Tenancy\TenantRole;
use App\Exceptions\AI\AiAuthenticationException;
use App\Models\AiProvider;
use App\Models\AiRun;
use App\Models\Message;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\AI\Providers\DeepSeekProvider;
use App\Services\AI\TenantAiConfigService;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;
use Tests\TestCase;

class DeepSeekProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.providers.deepseek.enabled' => true,
            'ai.providers.deepseek.base_url' => 'https://api.deepseek.com',
        ]);
    }

    public function test_tenant_can_select_and_save_deepseek_config(): void
    {
        ['tenant' => $tenant, 'user' => $owner] = $this->createTenantWithMember(role: TenantRole::Owner);
        $this->actingAs($owner);

        Volt::test('tenant.ai.configuration', ['tenant' => $tenant])
            ->set('provider', 'deepseek')
            ->set('model', 'deepseek-v4-flash')
            ->set('temperature', 0.2)
            ->set('maxOutputTokens', 400)
            ->set('timeoutSeconds', 15)
            ->set('enabled', true)
            ->set('credentialMode', 'platform_managed')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tenant_ai_configs', [
            'tenant_id' => $tenant->id,
            'model' => 'deepseek-v4-flash',
        ]);

        $providerId = AiProvider::query()->where('slug', 'deepseek')->value('id');
        $this->assertDatabaseHas('tenant_ai_configs', [
            'tenant_id' => $tenant->id,
            'provider_id' => $providerId,
        ]);
    }

    public function test_platform_managed_deepseek_key_is_used_server_side(): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'model' => 'deepseek-v4-flash',
                'choices' => [['message' => ['content' => 'DeepSeek reply'], 'finish_reason' => 'stop']],
                'usage' => ['prompt_tokens' => 4, 'completion_tokens' => 3, 'total_tokens' => 7],
            ], 200),
        ]);

        PlatformSetting::query()->create([
            'key' => 'platform_deepseek_api_key',
            'value' => ['encrypted' => Crypt::encryptString('sk-deepseek-platform-key')],
        ]);

        config(['ai.providers.deepseek.api_key' => null]);

        $response = app(DeepSeekProvider::class)->chat(new AiRequest(
            provider: 'deepseek',
            model: 'deepseek-v4-flash',
            messages: [new AiMessage('user', 'hello')],
            temperature: 0.2,
            maxOutputTokens: 100,
            timeoutSeconds: 5,
            requestId: (string) str()->uuid(),
            apiKey: null,
        ));

        $this->assertSame('DeepSeek reply', $response->content);
        $this->assertSame('deepseek', $response->provider);
        $this->assertSame('deepseek-v4-flash', $response->model);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.deepseek.com/chat/completions')
                && $request->hasHeader('Authorization', 'Bearer sk-deepseek-platform-key');
        });
    }

    public function test_orchestration_calls_deepseek_adapter_when_tenant_provider_is_deepseek(): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'model' => 'deepseek-v4-flash',
                'choices' => [['message' => ['content' => 'Orchestrated DeepSeek reply'], 'finish_reason' => 'stop']],
                'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 4, 'total_tokens' => 9],
            ], 200),
        ]);

        config(['ai.providers.deepseek.api_key' => 'sk-deepseek-test']);

        ['tenant' => $tenant, 'user' => $user, 'key' => $key] = $this->createWidgetReadyTenant();
        $this->configureTenantAi($tenant, $user, [
            'provider' => 'deepseek',
            'model' => 'deepseek-v4-flash',
            'credential_mode' => 'platform_managed',
        ]);

        $token = $this->widgetSessionToken($key);

        $response = $this->postJson('/widget/v1/messages', [
            'body' => 'What courses do you offer?',
            'request_id' => (string) str()->uuid(),
        ], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $this->assertSame('assistant', $response->json('reply.role'));
        $this->assertDatabaseHas('ai_runs', [
            'provider' => 'deepseek',
            'model' => 'deepseek-v4-flash',
            'status' => AiRunStatus::Success->value,
            'credential_source' => 'platform',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.deepseek.com'));
    }

    public function test_missing_deepseek_key_logs_auth_failure_safely(): void
    {
        config(['ai.providers.deepseek.api_key' => null]);

        ['tenant' => $tenant, 'user' => $user, 'key' => $key] = $this->createWidgetReadyTenant();
        $this->configureTenantAi($tenant, $user, [
            'provider' => 'deepseek',
            'model' => 'deepseek-v4-flash',
            'credential_mode' => 'platform_managed',
        ]);

        Http::fake();

        $token = $this->widgetSessionToken($key);

        $response = $this->postJson('/widget/v1/messages', [
            'body' => 'hello',
            'request_id' => (string) str()->uuid(),
        ], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $this->assertSame('system', $response->json('reply.role'));
        $this->assertDatabaseHas('ai_runs', [
            'provider' => 'deepseek',
            'model' => 'deepseek-v4-flash',
            'status' => AiRunStatus::Failed->value,
            'error_category' => AiErrorCategory::Auth->value,
        ]);

        Http::assertNothingSent();
    }

    public function test_deepseek_provider_auth_failure_maps_safely(): void
    {
        config(['ai.providers.deepseek.api_key' => 'sk-invalid']);

        Http::fake([
            'api.deepseek.com/*' => Http::response(['error' => 'invalid'], 401),
        ]);

        $this->expectException(AiAuthenticationException::class);
        app(DeepSeekProvider::class)->chat(new AiRequest(
            provider: 'deepseek',
            model: 'deepseek-v4-flash',
            messages: [new AiMessage('user', 'hello')],
            temperature: 0.2,
            maxOutputTokens: 100,
            timeoutSeconds: 5,
            requestId: (string) str()->uuid(),
            apiKey: 'sk-invalid',
        ));
    }

    public function test_platform_settings_store_deepseek_key_without_exposing_to_browser(): void
    {
        $admin = User::factory()->platformSuperAdmin()->create();

        app(PlatformSettingsService::class)->update([
            'platform_deepseek_api_key' => 'sk-deepseek-secret-1234',
        ], $admin);

        $this->actingAs($admin)
            ->get(route('platform.settings.index'))
            ->assertOk()
            ->assertDontSee('sk-deepseek-secret-1234', false)
            ->assertSee('Configured (value not shown)', false);
    }

    public function test_effective_config_uses_tenant_deepseek_settings_in_platform_managed_mode(): void
    {
        ['tenant' => $tenant, 'user' => $user] = $this->createTenantWithMember(role: TenantRole::Owner);
        $this->withTenantContext($user, $tenant);

        $this->configureTenantAi($tenant, $user, [
            'provider' => 'deepseek',
            'model' => 'deepseek-v4-pro',
            'temperature' => 0.35,
            'max_output_tokens' => 500,
            'timeout_seconds' => 20,
            'credential_mode' => 'platform_managed',
        ]);

        $effective = app(TenantAiConfigService::class)->getEffectiveConfig($tenant);

        $this->assertSame('deepseek', $effective['provider']);
        $this->assertSame('deepseek-v4-pro', $effective['model']);
        $this->assertSame(0.35, $effective['temperature']);
        $this->assertSame(500, $effective['max_output_tokens']);
        $this->assertSame(20, $effective['timeout_seconds']);
        $this->assertNull($effective['api_key']);
    }

    public function test_widget_message_flow_works_with_deepseek_http_fake(): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'model' => 'deepseek-v4-flash',
                'choices' => [['message' => ['content' => 'Widget DeepSeek reply'], 'finish_reason' => 'stop']],
                'usage' => ['prompt_tokens' => 6, 'completion_tokens' => 5, 'total_tokens' => 11],
            ], 200),
        ]);

        config(['ai.providers.deepseek.api_key' => 'sk-deepseek-widget']);

        ['tenant' => $tenant, 'user' => $user, 'key' => $key] = $this->createWidgetReadyTenant();
        $this->configureTenantAi($tenant, $user, [
            'provider' => 'deepseek',
            'model' => 'deepseek-v4-flash',
        ]);

        $token = $this->widgetSessionToken($key);

        $this->postJson('/widget/v1/messages', [
            'body' => 'Hello widget',
            'request_id' => (string) str()->uuid(),
        ], [
            'Origin' => 'http://127.0.0.1:8000',
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()
            ->assertJsonPath('reply.role', 'assistant');

        $this->assertSame(1, AiRun::query()->where('provider', 'deepseek')->count());
        $this->assertSame(0, Message::query()->where('role', MessageRole::Assistant->value)->where('body', 'like', '%sk-deepseek%')->count());
    }
}
