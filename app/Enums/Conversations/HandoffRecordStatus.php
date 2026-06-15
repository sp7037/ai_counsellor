<?php

namespace App\Enums\Conversations;

enum HandoffRecordStatus: string
{
    case Active = 'active';
    case Released = 'released';
    case Transferred = 'transferred';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Released => 'Released',
            self::Transferred => 'Transferred',
        };
    }
}
