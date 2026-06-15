<?php

namespace App\Enums\AI;

enum AiCredentialSource: string
{
    case Tenant = 'tenant';
    case Platform = 'platform';
}
