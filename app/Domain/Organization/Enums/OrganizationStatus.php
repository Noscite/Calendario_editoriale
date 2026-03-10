<?php

namespace App\Domain\Organization\Enums;

enum OrganizationStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case PastDue = 'past_due';
    case Trial = 'trial';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Attivo',
            self::Suspended => 'Sospeso',
            self::PastDue => 'Pagamento scaduto',
            self::Trial => 'Trial',
            self::Cancelled => 'Cancellato',
        };
    }
}
