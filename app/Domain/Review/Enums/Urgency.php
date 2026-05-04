<?php

declare(strict_types=1);

namespace App\Domain\Review\Enums;

enum Urgency: string
{
    case Low    = 'low';
    case Medium = 'medium';
    case High   = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low    => 'Bassa',
            self::Medium => 'Media',
            self::High   => 'Alta',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Low    => 'success',
            self::Medium => 'warning',
            self::High   => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Low    => 'heroicon-o-clock',
            self::Medium => 'heroicon-o-exclamation-circle',
            self::High   => 'heroicon-o-exclamation-triangle',
        };
    }
}
