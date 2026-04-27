<?php

namespace App\Domain\Organization\Enums;

enum OrganizationStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case PastDue = 'past_due';
    case Trial = 'trial';
    case Cancelled = 'cancelled';
    case PendingPayment = 'pending_payment';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Attivo',
            self::Suspended => 'Sospeso',
            self::PastDue => 'Pagamento scaduto',
            self::Trial => 'Trial',
            self::Cancelled => 'Cancellato',
            self::PendingPayment => 'In attesa di pagamento',
            self::Expired => 'Scaduto',
        };
    }
}
