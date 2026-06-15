<?php

namespace App\Enums\Configuration;

enum WidgetPosition: string
{
    case BottomRight = 'bottom_right';
    case BottomLeft = 'bottom_left';

    public function label(): string
    {
        return match ($this) {
            self::BottomRight => 'Bottom right',
            self::BottomLeft => 'Bottom left',
        };
    }
}
