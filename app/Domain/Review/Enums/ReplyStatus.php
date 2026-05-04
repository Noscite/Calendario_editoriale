<?php

declare(strict_types=1);

namespace App\Domain\Review\Enums;

enum ReplyStatus: string
{
    case Draft      = 'draft';
    case Approved   = 'approved';
    case Sending    = 'sending';
    case Sent       = 'sent';
    case Failed     = 'failed';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Draft      => 'Bozza',
            self::Approved   => 'Approvata',
            self::Sending    => 'Invio in corso',
            self::Sent       => 'Inviata',
            self::Failed     => 'Errore invio',
            self::Superseded => 'Sostituita',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft      => 'warning',
            self::Approved   => 'info',
            self::Sending    => 'primary',
            self::Sent       => 'success',
            self::Failed     => 'danger',
            self::Superseded => 'gray',
        };
    }

    public function isActive(): bool
    {
        return ! in_array($this, [self::Superseded, self::Failed], true);
    }
}
