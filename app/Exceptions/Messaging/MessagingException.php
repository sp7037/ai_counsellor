<?php

namespace App\Exceptions\Messaging;

use App\Enums\Messaging\MessagingFailureCategory;
use RuntimeException;

class MessagingException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly MessagingFailureCategory $category = MessagingFailureCategory::Unknown,
    ) {
        parent::__construct($message);
    }
}
