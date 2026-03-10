<?php

namespace App\Domain\Project\Enums;

enum ProjectStatus: string
{
    case Draft = 'draft';
    case Generating = 'generating';
    case Review = 'review';
    case Approved = 'approved';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Bozza',
            self::Generating => 'In generazione',
            self::Review => 'In revisione',
            self::Approved => 'Approvato',
            self::Published => 'Pubblicato',
        };
    }
}
