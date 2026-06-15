<?php

namespace App\Exceptions\Billing;

use App\Data\Billing\EntitlementResult;
use RuntimeException;

class EntitlementDeniedException extends RuntimeException
{
    public function __construct(public readonly EntitlementResult $result)
    {
        parent::__construct($result->denyReason());
    }
}
