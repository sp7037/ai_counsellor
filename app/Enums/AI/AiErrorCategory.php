<?php

namespace App\Enums\AI;

enum AiErrorCategory: string
{
    case Auth = 'auth';
    case RateLimit = 'rate_limit';
    case Timeout = 'timeout';
    case ContentPolicy = 'content_policy';
    case ProviderError = 'provider_error';
    case MissingKey = 'missing_key';
    case Disabled = 'disabled';
    case Internal = 'internal';
}
