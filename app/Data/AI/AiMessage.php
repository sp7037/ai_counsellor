<?php

namespace App\Data\AI;

readonly class AiMessage
{
    public function __construct(
        public string $role,
        public string $content,
    ) {}
}
