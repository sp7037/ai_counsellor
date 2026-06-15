<?php

namespace App\Enums\Billing;

enum PaymentOrderStatus: string
{
    case Pending = 'pending';
    case Created = 'created';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Created => 'Awaiting payment',
            self::Paid => 'Paid',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::Created], true);
    }
}
