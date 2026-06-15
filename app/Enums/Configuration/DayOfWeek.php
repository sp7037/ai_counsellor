<?php

namespace App\Enums\Configuration;

enum DayOfWeek: int
{
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 7;

    public function label(): string
    {
        return match ($this) {
            self::Monday => 'Monday',
            self::Tuesday => 'Tuesday',
            self::Wednesday => 'Wednesday',
            self::Thursday => 'Thursday',
            self::Friday => 'Friday',
            self::Saturday => 'Saturday',
            self::Sunday => 'Sunday',
        };
    }

    public static function fromCarbonDay(int $carbonDayOfWeek): self
    {
        return match ($carbonDayOfWeek) {
            0 => self::Sunday,
            1 => self::Monday,
            2 => self::Tuesday,
            3 => self::Wednesday,
            4 => self::Thursday,
            5 => self::Friday,
            6 => self::Saturday,
            default => self::Monday,
        };
    }
}
