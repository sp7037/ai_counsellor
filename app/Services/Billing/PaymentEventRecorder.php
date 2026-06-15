<?php

namespace App\Services\Billing;

use App\Enums\Billing\PaymentEventType;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentOrder;

class PaymentEventRecorder
{
    public function record(
        PaymentOrder $order,
        PaymentEventType $type,
        ?string $source = null,
        array $metadata = [],
        ?Payment $payment = null,
    ): PaymentEvent {
        return PaymentEvent::query()->create([
            'tenant_id' => $order->tenant_id,
            'payment_order_id' => $order->id,
            'payment_id' => $payment?->id,
            'event_type' => $type->value,
            'source' => $source,
            'metadata' => $metadata === [] ? null : $metadata,
            'created_at' => now(),
        ]);
    }
}
