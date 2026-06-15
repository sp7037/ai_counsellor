<?php

namespace App\Enums;

enum PlatformRole: string
{
    case SuperAdmin = 'super_admin';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Platform Super Admin',
        };
    }
}
