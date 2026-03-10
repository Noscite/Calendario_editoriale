<?php

namespace App\Domain\Post\Enums;

enum PostStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Bozza',
            self::Approved => 'Approvato',
        };
    }
}
