<?php

declare(strict_types=1);

namespace App\Domain\Post\Enums;

/**
 * Stato di pubblicazione di un post.
 *
 * - Draft:      bozza, non ancora schedulato
 * - Scheduled:  schedulato, in attesa di pubblicazione
 * - Publishing: lock ottimistico — il worker ha preso in carico il post
 * - Published:  pubblicato con successo
 * - Failed:     pubblicazione fallita
 */
enum PublicationStatus: string
{
    case Draft      = 'draft';
    case Scheduled  = 'scheduled';
    case Publishing = 'publishing';
    case Published  = 'published';
    case Failed     = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Draft      => 'Bozza',
            self::Scheduled  => 'Programmato',
            self::Publishing => 'In pubblicazione',
            self::Published  => 'Pubblicato',
            self::Failed     => 'Fallito',
        };
    }
}
