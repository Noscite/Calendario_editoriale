<?php

namespace App\Domain\Post\Enums;

enum PublicationStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Bozza',
            self::Scheduled => 'Programmato',
            self::Published => 'Pubblicato',
            self::Failed => 'Fallito',
        };
    }
}
