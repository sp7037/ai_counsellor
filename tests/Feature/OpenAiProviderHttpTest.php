<?php

namespace Tests\Feature;

use App\Data\AI\AiMessage;
use App\Data\AI\AiRequest;
use App\Exceptions\AI\AiAuthenticationException;
use App\Exceptions\AI\AiContentPolicyException;
use App\Exceptions\AI\AiProviderException;
use App\Exceptions\AI\AiRateLimitException;
use App\Services\AI\Providers\OpenAiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class OpenAiProviderHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_ai_success_response_is_schema_validated(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [['message' => ['content' => 'Hello'], 'finish_reason' => 'stop']],
                'usage' => ['prompt_tokens' => 3, 'completion_tokens' => 2, 'total_tokens' => 5],
            ], 200),
        ]);

        config(['ai.providers.openai.api_key' => 'sk-test-key']);

        $response = app(OpenAiProvider::class)->chat($this->request());

        $this->assertSame('Hello', $response->content);
        $this->assertSame(3, $response->usage->inputTokens);
    }

    public function test_open_ai_authentication_failure_maps_safely(): void
    {
        config(['ai.providers.openai.api_key' => 'sk-test-key']);

        Http::fake([
            'api.openai.com/*' => Http::response(['error' => 'invalid'], 401),
        ]);

        $this->expectException(AiAuthenticationException::class);
        app(OpenAiProvider::class)->chat($this->request());
    }

    public function test_open_ai_rate_limit_failure_maps_safely(): void
    {
        config(['ai.providers.openai.api_key' => 'sk-test-key']);

        Http::fake([
            'api.openai.com/*' => Http::response(['error' => 'rate'], 429),
        ]);

        $this->expectException(AiRateLimitException::class);
        app(OpenAiProvider::class)->chat($this->request());
    }

    public function test_open_ai_server_failure_maps_safely(): void
    {
        config(['ai.providers.openai.api_key' => 'sk-test-key']);

        Http::fake([
            'api.openai.com/*' => Http::response(['error' => 'server'], 500),
        ]);

        $this->expectException(AiProviderException::class);
        app(OpenAiProvider::class)->chat($this->request());
    }

    public function test_open_ai_malformed_json_fails_safely(): void
    {
        config(['ai.providers.openai.api_key' => 'sk-test-key']);

        Http::fake([
            'api.openai.com/*' => Http::response('not-json', 200),
        ]);

        $this->expectException(AiProviderException::class);
        app(OpenAiProvider::class)->chat($this->request());
    }

    public function test_open_ai_empty_content_filter_maps_to_content_policy_exception(): void
    {
        config(['ai.providers.openai.api_key' => 'sk-test-key']);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => ''], 'finish_reason' => 'content_filter']],
            ], 200),
        ]);

        $this->expectException(AiContentPolicyException::class);
        app(OpenAiProvider::class)->chat($this->request());
    }

    public function test_open_ai_provider_does_not_log_authorization_header_on_failure(): void
    {
        Log::spy();
        config(['ai.providers.openai.api_key' => 'sk-http-redact-secret']);

        Http::fake([
            'api.openai.com/*' => Http::response(['error' => 'bad'], 500),
        ]);

        try {
            app(OpenAiProvider::class)->chat($this->request());
        } catch (AiProviderException) {
        }

        Log::shouldNotHaveReceived('error', function ($message, $context = null): bool {
            $encoded = json_encode($context);

            return is_string($encoded) && str_contains($encoded, 'sk-http-redact-secret');
        });
    }

    public function test_disallowed_model_is_rejected_before_http_call(): void
    {
        config([
            'ai.providers.openai.api_key' => 'sk-test-key',
            'ai.allowed_models' => ['gpt-4o-mini'],
        ]);

        Http::fake();

        $this->expectException(AiProviderException::class);

        app(OpenAiProvider::class)->chat(new AiRequest(
            provider: 'openai',
            model: 'gpt-unlisted-model',
            messages: [new AiMessage('user', 'hello')],
            temperature: 0.2,
            maxOutputTokens: 100,
            timeoutSeconds: 5,
            requestId: (string) str()->uuid(),
            apiKey: 'sk-test-key',
        ));
    }

    private function request(): AiRequest
    {
        return new AiRequest(
            provider: 'openai',
            model: 'gpt-4o-mini',
            messages: [new AiMessage('user', 'hello')],
            temperature: 0.2,
            maxOutputTokens: 100,
            timeoutSeconds: 5,
            requestId: (string) str()->uuid(),
            apiKey: 'sk-test-key',
        );
    }
}
