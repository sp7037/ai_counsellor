<?php

namespace App\Services\AI\Providers;

use App\Contracts\AI\AiProviderContract;
use App\Data\AI\AiRequest;
use App\Data\AI\AiResponse;
use App\Data\AI\AiUsage;
use App\Exceptions\AI\AiTimeoutException;

class FakeAiProvider implements AiProviderContract
{
    public function provider(): string
    {
        return 'fake';
    }

    public function supportsTools(): bool
    {
        return false;
    }

    public function chat(AiRequest $request): AiResponse
    {
        if (str_contains(strtolower($request->messages[array_key_last($request->messages)]->content ?? ''), 'trigger timeout')) {
            throw new AiTimeoutException('Fake timeout');
        }

        return new AiResponse(
            provider: 'fake',
            model: $request->model,
            content: 'AI reply: '.$request->messages[array_key_last($request->messages)]->content,
            usage: new AiUsage(inputTokens: 10, outputTokens: 8, totalTokens: 18, latencyMs: 20),
        );
    }
}
