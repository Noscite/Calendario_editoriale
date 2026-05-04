<?php

declare(strict_types=1);

namespace App\Domain\Review\Enums;

enum ReplyTone: string
{
    case BrandDefault = 'brand_default';
    case Empathetic   = 'empathetic';
    case Professional = 'professional';
    case Solution     = 'solution';
    case Gratitude    = 'gratitude';
    case Formal       = 'formal';

    public function label(): string
    {
        return match ($this) {
            self::BrandDefault => 'Tono del brand',
            self::Empathetic   => 'Empatico',
            self::Professional => 'Professionale',
            self::Solution     => 'Risolutivo',
            self::Gratitude    => 'Ringraziamento',
            self::Formal       => 'Formale',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::BrandDefault => 'Usa il tono di voce di base del brand.',
            self::Empathetic   => 'Ascolto attivo, riconoscimento dei sentimenti del cliente.',
            self::Professional => 'Distaccato e professionale, niente familiarità.',
            self::Solution     => 'Focus sulla risoluzione concreta del problema.',
            self::Gratitude    => 'Caloroso, esprime gratitudine genuina.',
            self::Formal       => 'Registro formale, lessico curato.',
        };
    }
}
