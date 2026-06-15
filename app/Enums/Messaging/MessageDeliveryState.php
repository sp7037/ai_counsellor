<?php

namespace App\Enums\Messaging;

enum MessageDeliveryState: string
{
    case Pending = 'pending';
    case Submitted = 'submitted';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Submitted => 'Submitted',
            self::Sent => 'Sent',
            self::Delivered => 'Delivered',
            self::Read => 'Read',
            self::Failed => 'Failed',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Pending => 0,
            self::Submitted => 1,
            self::Sent => 2,
            self::Delivered => 3,
            self::Read => 4,
            self::Failed => -1,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Delivered, self::Read, self::Failed], true);
    }
}
