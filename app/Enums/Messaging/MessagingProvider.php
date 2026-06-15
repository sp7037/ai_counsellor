<?php

namespace App\Enums\Messaging;

enum MessagingProvider: string
{
    case Meta = 'meta';
    case Fake = 'fake';

    public function label(): string
    {
        return match ($this) {
            self::Meta => 'Meta WhatsApp Cloud API',
            self::Fake => 'Fake (testing)',
        };
    }
}
