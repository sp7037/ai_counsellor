<?php

namespace App\Enums\Configuration;

enum LauncherMode: string
{
    case Circle = 'circle';
    case Card = 'card';
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Circle => 'Circle button',
            self::Card => 'Card launcher',
            self::Disabled => 'Disabled',
        };
    }
}
