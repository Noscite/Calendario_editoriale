<?php

declare(strict_types=1);

namespace App\Domain\Generation\Presets;

use App\Domain\Post\Enums\PostType;

/**
 * Preset editoriale applicabile alla generazione di un calendario.
 *
 * - Standard      → comportamento storico, nessun override della distribuzione.
 * - B2BAuthority  → cadenza settimanale B2B (Lun→Ven) con un tipo di post
 *                   dedicato per ogni giorno lavorativo, iniettata come vincolo
 *                   di distribuzione nel PromptBuilder.
 */
enum EditorialPreset: string
{
    case Standard     = 'standard';
    case B2BAuthority = 'b2b_authority';

    public function label(): string
    {
        return match ($this) {
            self::Standard     => 'Standard',
            self::B2BAuthority => 'B2B Authority (Lun→Ven)',
        };
    }

    /**
     * Mappa giorno lavorativo → PostType per la settimana editoriale.
     * Per Standard ritorna array vuoto (nessun override).
     *
     * @return array<string, PostType>
     */
    public function weeklySchedule(): array
    {
        return match ($this) {
            self::B2BAuthority => [
                'lunedì'    => PostType::Engagement,
                'martedì'   => PostType::Educational,
                'mercoledì' => PostType::LeadMagnet,
                'giovedì'   => PostType::SocialProof,
                'venerdì'   => PostType::BehindTheScenes,
            ],
            self::Standard => [],
        };
    }

    /**
     * Per dropdown Filament/React: ['standard' => 'Standard', ...]
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $p) => [$p->value => $p->label()])
            ->toArray();
    }
}
