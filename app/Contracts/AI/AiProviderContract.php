<?php

namespace App\Contracts\AI;

use App\Data\AI\AiRequest;
use App\Data\AI\AiResponse;

interface AiProviderContract
{
    public function provider(): string;

    public function chat(AiRequest $request): AiResponse;

    public function supportsTools(): bool;
}
