<?php

namespace App\Enums\Messaging;

enum MessagingIntegrationStatus: string
{
    case Disabled = 'disabled';
    case Pending = 'pending';
    case Connected = 'connected';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Disabled => 'Disabled',
            self::Pending => 'Pending verification',
            self::Connected => 'Connected',
            self::Error => 'Error',
        };
    }
}
