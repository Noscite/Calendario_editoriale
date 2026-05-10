<?php

declare(strict_types=1);

namespace App\Domain\Campaign\Enums;

enum CampaignStatus: string
{
    case Draft     = 'draft';
    case Planning  = 'planning';
    case Active    = 'active';
    case Completed = 'completed';
    case Archived  = 'archived';

    /**
     * Stati che contano per il plan limit "campagne attive".
     */
    public function isActive(): bool
    {
        return in_array($this, [self::Planning, self::Active], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft     => 'Bozza',
            self::Planning  => 'In pianificazione',
            self::Active    => 'Attiva',
            self::Completed => 'Conclusa',
            self::Archived  => 'Archiviata',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft     => 'gray',
            self::Planning  => 'warning',
            self::Active    => 'success',
            self::Completed => 'info',
            self::Archived  => 'gray',
        };
    }

    /**
     * State machine: ritorna gli stati raggiungibili da quello corrente.
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft     => [self::Planning, self::Archived],
            self::Planning  => [self::Active, self::Draft, self::Archived],
            self::Active    => [self::Completed, self::Archived],
            self::Completed => [self::Archived],
            self::Archived  => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $s) => [$s->value => $s->label()])
            ->toArray();
    }
}
