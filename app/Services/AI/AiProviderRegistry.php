<?php

namespace App\Services\AI;

use App\Contracts\AI\AiProviderContract;
use App\Exceptions\AI\AiProviderException;
use App\Services\AI\Providers\DeepSeekProvider;
use App\Services\AI\Providers\FakeAiProvider;
use App\Services\AI\Providers\OpenAiProvider;

class AiProviderRegistry
{
    public function resolve(string $provider): AiProviderContract
    {
        return match ($provider) {
            'openai' => app(OpenAiProvider::class),
            'deepseek' => app(DeepSeekProvider::class),
            'fake' => app(FakeAiProvider::class),
            default => throw new AiProviderException('Unsupported AI provider.'),
        };
    }
}
