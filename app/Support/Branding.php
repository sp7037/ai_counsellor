<?php

namespace App\Support;

class Branding
{
    public static function logoPath(): string
    {
        if (is_file(public_path('images/Ai_counsellor_logo.PNG'))) {
            return 'images/Ai_counsellor_logo.PNG';
        }

        if (is_file(public_path('images/Ai_counsellor_logo.png'))) {
            return 'images/Ai_counsellor_logo.png';
        }

        return 'images/Ai_counsellor_logo.png';
    }

    public static function faviconPath(): string
    {
        if (is_file(public_path('images/favicon.png'))) {
            return 'images/favicon.png';
        }

        return self::logoPath();
    }
}
