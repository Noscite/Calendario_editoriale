<?php

declare(strict_types=1);

namespace App\Domain\Review\Enums;

enum MarketingOpportunity: string
{
    case None        = 'none';
    case Recovery    = 'recovery';
    case Advocacy    = 'advocacy';
    case Upsell      = 'upsell';
    case Testimonial = 'testimonial';

    public function label(): string
    {
        return match ($this) {
            self::None        => 'Nessuna',
            self::Recovery    => 'Recupero cliente',
            self::Advocacy    => 'Ambassador',
            self::Upsell      => 'Upsell',
            self::Testimonial => 'Testimonial',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::None        => 'Nessuna opportunità di marketing rilevante',
            self::Recovery    => 'Critica recuperabile: rispondere con cura per recuperare il cliente',
            self::Advocacy    => 'Cliente entusiasta: coinvolgilo come ambassador del brand',
            self::Upsell      => 'Cliente soddisfatto pronto ad acquistare di più',
            self::Testimonial => 'Recensione perfetta da promuovere come case study',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::None        => 'gray',
            self::Recovery    => 'warning',
            self::Advocacy    => 'success',
            self::Upsell      => 'info',
            self::Testimonial => 'primary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::None        => 'heroicon-o-minus',
            self::Recovery    => 'heroicon-o-lifebuoy',
            self::Advocacy    => 'heroicon-o-megaphone',
            self::Upsell      => 'heroicon-o-arrow-trending-up',
            self::Testimonial => 'heroicon-o-trophy',
        };
    }
}
