<?php

namespace App\Services\AI\Providers;

class OpenAiProvider extends OpenAiCompatibleChatProvider
{
    public function provider(): string
    {
        return 'openai';
    }

    protected function displayName(): string
    {
        return 'OpenAI';
    }
}
