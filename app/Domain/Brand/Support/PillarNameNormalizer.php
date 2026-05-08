<?php

declare(strict_types=1);

namespace App\Domain\Brand\Support;

use Illuminate\Support\Str;

/**
 * Normalizzazione canonica del nome di un content pillar.
 *
 * Single source of truth condivisa tra:
 *  - ClaudeContentGenerator::matchPillar()      → coercion runtime AI
 *  - BrandService::mergeDefaultPillars()         → dedup case-insensitive sul merge
 *  - Validazione frontend pillar (via API echo)
 *
 * La normalizzazione è volutamente "tolerant": fold accenti → ASCII,
 * lowercase, qualunque non-alfanumerico/underscore → spazio, collapse spazi.
 */
final class PillarNameNormalizer
{
    /**
     * Normalizza il name di un pillar a una forma canonica per il confronto.
     * Ritorna stringa vuota se l'input è vuoto/whitespace-only.
     */
    public static function normalize(?string $name): string
    {
        if ($name === null) {
            return '';
        }

        $s = (string) Str::ascii($name);
        $s = mb_strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9\s_]+/', ' ', $s) ?? $s;
        $s = preg_replace('/[\s_]+/', ' ', $s) ?? $s;

        return trim($s);
    }

    /**
     * True se i due name sono equivalenti dopo normalizzazione.
     */
    public static function equals(?string $a, ?string $b): bool
    {
        $na = self::normalize($a);
        $nb = self::normalize($b);

        return $na !== '' && $na === $nb;
    }
}
