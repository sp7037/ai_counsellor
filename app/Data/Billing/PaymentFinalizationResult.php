<?php

namespace App\Data\Billing;

use App\Models\Payment;
use App\Models\PaymentOrder;
use App\Models\Subscription;

readonly class PaymentFinalizationResult
{
    public function __construct(
        public PaymentOrder $order,
        public Payment $payment,
        public ?Subscription $subscription,
        public bool $wasAlreadyFinalized,
    ) {}
}
