<?php

namespace App\Domain\Post\Enums;

enum Platform: string
{
    case LinkedIn = 'linkedin';
    case Instagram = 'instagram';
    case Facebook = 'facebook';
    case GoogleBusiness = 'google_business';

    public function label(): string
    {
        return match ($this) {
            self::LinkedIn => 'LinkedIn',
            self::Instagram => 'Instagram',
            self::Facebook => 'Facebook',
            self::GoogleBusiness => 'Google Business',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::LinkedIn => '💼',
            self::Instagram => '📸',
            self::Facebook => '👤',
            self::GoogleBusiness => '🏢',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::LinkedIn => '#0A66C2',
            self::Instagram => '#E4405F',
            self::Facebook => '#1877F2',
            self::GoogleBusiness => '#4285F4',
        };
    }
}
