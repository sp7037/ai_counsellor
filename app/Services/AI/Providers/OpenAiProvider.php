<?php

namespace App\Services\AI\Providers;

use App\Contracts\AI\AiProviderContract;
use App\Data\AI\AiRequest;
use App\Data\AI\AiResponse;
use App\Data\AI\AiUsage;
use App\Exceptions\AI\AiAuthenticationException;
use App\Exceptions\AI\AiContentPolicyException;
use App\Exceptions\AI\AiProviderException;
use App\Exceptions\AI\AiRateLimitException;
use App\Exceptions\AI\AiTimeoutException;
use App\Services\AI\SafeAiExceptionMapper;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class OpenAiProvider implements AiProviderContract
{
    public function __construct(
        private readonly SafeAiExceptionMapper $exceptionMapper,
    ) {}

    public function provider(): string
    {
        return 'openai';
    }

    public function supportsTools(): bool
    {
        return false;
    }

    public function chat(AiRequest $request): AiResponse
    {
        $apiKey = $request->apiKey ?: config('ai.providers.openai.api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new AiAuthenticationException('OpenAI API key is not configured.');
        }

        $this->assertAllowedModel($request->model);

        $started = microtime(true);

        try {
            $response = Http::baseUrl((string) config('ai.providers.openai.base_url'))
                ->withToken($apiKey)
                ->timeout($request->timeoutSeconds)
                ->connectTimeout((int) config('ai.connect_timeout_seconds', 5))
                ->retry((int) config('ai.http_retries', 0), 0, throw: false)
                ->acceptJson()
                ->post('/chat/completions', [
                    'model' => $request->model,
                    'messages' => array_map(
                        fn ($message) => ['role' => $message->role, 'content' => $message->content],
                        $request->messages
                    ),
                    'temperature' => $this->boundTemperature($request->temperature),
                    'max_tokens' => $this->boundMaxOutputTokens($request->maxOutputTokens),
                    'user' => $request->requestId,
                ]);
        } catch (ConnectionException $exception) {
            throw new AiTimeoutException('OpenAI request timed out.');
        } catch (\Throwable $exception) {
            throw new AiTimeoutException($this->exceptionMapper->safeMessage($exception));
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new AiAuthenticationException('OpenAI authentication failed.');
        }

        if ($response->status() === 429) {
            throw new AiRateLimitException('OpenAI rate limit reached.');
        }

        if (! $response->successful()) {
            throw new AiProviderException('OpenAI request failed.');
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new AiProviderException('OpenAI returned malformed JSON.');
        }

        $choice = $payload['choices'][0] ?? null;
        $content = trim((string) ($choice['message']['content'] ?? ''));
        $finish = (string) ($choice['finish_reason'] ?? '');

        if ($content === '' && $finish === 'content_filter') {
            throw new AiContentPolicyException('OpenAI content policy refusal.');
        }

        if ($content === '') {
            throw new AiProviderException('OpenAI returned empty content.');
        }

        $usage = is_array($payload['usage'] ?? null) ? $payload['usage'] : [];
        $latencyMs = (int) round((microtime(true) - $started) * 1000);

        return new AiResponse(
            provider: $this->provider(),
            model: (string) ($payload['model'] ?? $request->model),
            content: $content,
            usage: new AiUsage(
                inputTokens: isset($usage['prompt_tokens']) ? (int) $usage['prompt_tokens'] : null,
                outputTokens: isset($usage['completion_tokens']) ? (int) $usage['completion_tokens'] : null,
                totalTokens: isset($usage['total_tokens']) ? (int) $usage['total_tokens'] : null,
                latencyMs: $latencyMs,
            ),
            refused: false,
            finishReason: $finish !== '' ? $finish : null,
        );
    }

    private function assertAllowedModel(string $model): void
    {
        $allowed = (array) config('ai.allowed_models', []);

        if ($allowed !== [] && ! in_array($model, $allowed, true)) {
            throw new AiProviderException('Selected model is not allowed.');
        }
    }

    private function boundTemperature(float $temperature): float
    {
        return max(
            (float) config('ai.min_temperature', 0.0),
            min((float) config('ai.max_temperature', 1.2), $temperature),
        );
    }

    private function boundMaxOutputTokens(int $tokens): int
    {
        return max(1, min((int) config('ai.max_output_tokens_limit', 1200), $tokens));
    }
}
