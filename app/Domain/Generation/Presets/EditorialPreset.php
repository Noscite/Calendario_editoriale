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
     * Orario ottimale per (giorno, piattaforma) sotto questo preset.
     *
     * @param  int     $dayIndex  0=lunedì … 6=domenica (coerente con weeklySchedule())
     * @param  string  $platform  slug piattaforma (es. 'linkedin')
     * @return string|null 'HH:MM' oppure null se il preset non specializza l'orario
     *                     per quella coppia (→ si usa il comportamento esistente).
     */
    public function slotTime(int $dayIndex, string $platform): ?string
    {
        return $this->slotTimesByPlatform()[strtolower($platform)][$dayIndex] ?? null;
    }

    /**
     * Mappa piattaforma → (indice giorno → orario) per il preset. Definita come
     * dato, non come match annidati. Piattaforme/giorni non presenti → nessun
     * override (slotTime() ritorna null).
     *
     * Razionale orari B2BAuthority/LinkedIn (dati 2026 su ~8M post B2B):
     * martedì–giovedì concentra ~68% dell'engagement B2B; la finestra mattutina
     * 8–11 è quando i decision maker sono attivi; il venerdì ha una finestra
     * stretta che si chiude verso pranzo, quindi orario più anticipato.
     * Il preset è tarato su LinkedIn: sulle altre piattaforme nessun override.
     *
     * @return array<string, array<int, string>>
     */
    private function slotTimesByPlatform(): array
    {
        return match ($this) {
            self::B2BAuthority => [
                'linkedin' => [
                    0 => '09:00', // lunedì    — Engagement
                    1 => '08:30', // martedì   — Educational
                    2 => '09:00', // mercoledì — LeadMagnet
                    3 => '08:30', // giovedì   — SocialProof
                    4 => '08:00', // venerdì   — BehindTheScenes
                ],
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
