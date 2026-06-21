<?php

namespace App\Services\AI\Providers;

class DeepSeekProvider extends OpenAiCompatibleChatProvider
{
    public function provider(): string
    {
        return 'deepseek';
    }

    protected function displayName(): string
    {
        return 'DeepSeek';
    }
}
