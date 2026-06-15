<?php

namespace App\Exceptions\Billing;

use App\Enums\Billing\PaymentFailureCategory;
use RuntimeException;

class PaymentException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly PaymentFailureCategory $category = PaymentFailureCategory::Unknown,
    ) {
        parent::__construct($message);
    }
}
