<?php

namespace App\Enums\Configuration;

enum StudyMode: string
{
    case Regular = 'regular';
    case Online = 'online';
    case Distance = 'distance';
    case Hybrid = 'hybrid';
    case SelfStudy = 'self_study';

    public function label(): string
    {
        return match ($this) {
            self::Regular => 'Regular',
            self::Online => 'Online',
            self::Distance => 'Distance / Open',
            self::Hybrid => 'Hybrid',
            self::SelfStudy => 'Self study',
        };
    }
}
