<?php

declare(strict_types=1);

namespace App\Domain\Review\Enums;

enum Sentiment: string
{
    case Positive = 'positive';
    case Neutral  = 'neutral';
    case Negative = 'negative';
    case Mixed    = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::Positive => 'Positivo',
            self::Neutral  => 'Neutro',
            self::Negative => 'Negativo',
            self::Mixed    => 'Misto',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Positive => 'success',
            self::Neutral  => 'gray',
            self::Negative => 'danger',
            self::Mixed    => 'warning',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Positive => 'heroicon-o-face-smile',
            self::Neutral  => 'heroicon-o-minus-circle',
            self::Negative => 'heroicon-o-face-frown',
            self::Mixed    => 'heroicon-o-arrows-right-left',
        };
    }
}
