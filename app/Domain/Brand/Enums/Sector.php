<?php

declare(strict_types=1);

namespace App\Domain\Brand\Enums;

enum Sector: string
{
    case Turismo      = 'turismo';
    case Food         = 'food';
    case Fashion      = 'fashion';
    case Tech         = 'tech';
    case Arte         = 'arte';
    case Salute       = 'salute';
    case Legale       = 'legale';
    case Finanza      = 'finanza';
    case Retail       = 'retail';
    case Education    = 'education';
    case B2BServizi   = 'b2b_servizi';
    case RealEstate   = 'real_estate';
    case Automotive   = 'automotive';

    public function label(): string
    {
        return match ($this) {
            self::Turismo    => 'Turismo / Pro Loco / Territoriale',
            self::Food       => 'Food / Ristorazione',
            self::Fashion    => 'Fashion / Beauty',
            self::Tech       => 'Tech / SaaS',
            self::Arte       => 'Arte / Design / Cultura',
            self::Salute     => 'Salute / Psicologia / Medicina',
            self::Legale     => 'Legale / Notai',
            self::Finanza    => 'Finanza / Consulenza / Banche / Assicurazioni',
            self::Retail     => 'Retail / E-commerce',
            self::Education  => 'Education / Formazione',
            self::B2BServizi => 'B2B Servizi',
            self::RealEstate => 'Real Estate',
            self::Automotive => 'Automotive',
        };
    }

    /**
     * Per dropdown Filament: ['turismo' => 'Turismo / Pro Loco / Territoriale', ...]
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $s) => [$s->value => $s->label()])
            ->toArray();
    }
}
