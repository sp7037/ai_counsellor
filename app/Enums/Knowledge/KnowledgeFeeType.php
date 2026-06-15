<?php

namespace App\Enums\Knowledge;

enum KnowledgeFeeType: string
{
    case Exact = 'exact';
    case StartingFrom = 'starting_from';
    case Range = 'range';

    public function label(): string
    {
        return match ($this) {
            self::Exact => 'Exact amount',
            self::StartingFrom => 'Starting from',
            self::Range => 'Range',
        };
    }
}
