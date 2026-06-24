<?php

namespace App\Enums\Configuration;

enum LauncherAnimation: string
{
    case None = 'none';
    case SoftSlideUp = 'soft_slide_up';
    case GentlePulse = 'gentle_pulse';
    case SoftBounceOnce = 'soft_bounce_once';

    public function label(): string
    {
        return match ($this) {
            self::None => 'None',
            self::SoftSlideUp => 'Soft slide-up',
            self::GentlePulse => 'Gentle pulse',
            self::SoftBounceOnce => 'Soft bounce once',
        };
    }
}
