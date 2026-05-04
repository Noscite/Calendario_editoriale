<?php

declare(strict_types=1);

namespace App\Domain\Review\Enums;

enum ReviewStatus: string
{
    case New     = 'new';
    case Scored  = 'scored';
    case Drafted = 'drafted';
    case Replied = 'replied';
    case Ignored = 'ignored';

    public function label(): string
    {
        return match ($this) {
            self::New     => 'Nuova',
            self::Scored  => 'Valutata',
            self::Drafted => 'Bozza risposta',
            self::Replied => 'Risposta inviata',
            self::Ignored => 'Ignorata',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New     => 'warning',
            self::Scored  => 'info',
            self::Drafted => 'primary',
            self::Replied => 'success',
            self::Ignored => 'gray',
        };
    }
}
