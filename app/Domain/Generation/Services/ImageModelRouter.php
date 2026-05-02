<?php

declare(strict_types=1);

namespace App\Domain\Generation\Services;

use App\Domain\Organization\Models\Organization;
use App\Domain\Post\Models\Post;
use Illuminate\Support\Facades\Log;

/**
 * ImageModelRouter
 *
 * Seleziona quale modello OpenAI usare per generare l'immagine di un Post,
 * in base a:
 *   - piano subscription dell'organization del brand del progetto del post
 *   - pillar del post (override automatico se è "hero" — lancio, annuncio, ecc.)
 *
 * MAPPATURA PIANO → TIER:
 *   small      → gpt-image-1-mini  quality=medium  $0.011/img
 *   standard   → gpt-image-1       quality=medium  $0.040/img
 *   pro        → gpt-image-1       quality=medium  $0.040/img
 *   unlimited  → gpt-image-1       quality=high    $0.167/img
 *   default    → gpt-image-1       quality=medium  $0.040/img
 *
 * OVERRIDE HERO PILLAR:
 *   Se il pillar contiene una hero keyword, e il tier corrente è MEDIUM (Small),
 *   bump a gpt-image-1 quality=medium ($0.04). Standard/Pro/Unlimited non cambiano.
 *
 * FEATURE FLAG:
 *   Se services.openai.image_router_enabled === false → ritorna sempre il default
 *   "image_default_model" con quality=high (comportamento legacy).
 *
 * Stateless, container-resolvable. Nessuna dipendenza nel costruttore.
 */
final class ImageModelRouter
{
    /** Hero pillar keywords (case insensitive, match parziale). */
    public const HERO_KEYWORDS = [
        'lancio', 'launch', 'annuncio', 'announcement',
        'milestone', 'evento', 'event', 'anniversario',
        'anniversary', 'inaugurazione', 'opening',
        'novità importante', 'big news',
    ];

    /** Tier identifiers (logici, non OpenAI strings). */
    public const TIER_PREMIUM = 'premium';   // Unlimited
    public const TIER_HIGH    = 'high';      // Standard, Pro, hero override
    public const TIER_MEDIUM  = 'medium';    // Small default

    /**
     * Seleziona il modello per un Post.
     *
     * @return array{
     *   tier: string,
     *   plan: string,
     *   openai_model: string,
     *   quality: string,
     *   estimated_cost: float,
     *   hero_override: bool
     * }
     */
    public function selectForPost(Post $post): array
    {
        // Kill switch
        if (! (bool) config('services.openai.image_router_enabled', true)) {
            return $this->legacyDefault();
        }

        $planName = $this->resolvePlanFromPost($post);
        $pillar   = $post->pillar ?? '';

        return $this->selectFromPlanAndPillar($planName, $pillar);
    }

    /**
     * Selezione pura (per testing).
     *
     * @return array{tier:string, plan:string, openai_model:string, quality:string, estimated_cost:float, hero_override:bool}
     */
    public function selectFromPlanAndPillar(?string $planName, ?string $pillar): array
    {
        $planNorm  = mb_strtolower(trim((string) $planName));
        $isHero    = self::isHeroPillar($pillar);

        // Tier default per piano
        $tier = match ($planNorm) {
            'small'                   => self::TIER_MEDIUM,
            'standard', 'pro'         => self::TIER_HIGH,
            'unlimited'               => self::TIER_PREMIUM,
            default                   => self::TIER_HIGH,
        };

        // Override hero: bump dal MEDIUM al HIGH. Altri tier invariati.
        $heroOverride = false;
        if ($isHero && $tier === self::TIER_MEDIUM) {
            $tier         = self::TIER_HIGH;
            $heroOverride = true;
        }

        return $this->resolveTier($tier, $planNorm ?: 'unknown', $heroOverride);
    }

    /** Verifica se un pillar contiene una hero keyword. */
    public static function isHeroPillar(?string $pillar): bool
    {
        if ($pillar === null || $pillar === '') return false;
        $p = mb_strtolower($pillar);
        foreach (self::HERO_KEYWORDS as $kw) {
            if (str_contains($p, $kw)) return true;
        }
        return false;
    }

    /**
     * Resolve plan navigando Post → Project → Brand → Organization → Plan.
     * Ritorna null se la catena è incompleta.
     */
    private function resolvePlanFromPost(Post $post): ?string
    {
        try {
            $brand = $post->project?->brand;
            if (! $brand) return null;

            $org = Organization::find($brand->organization_id);
            if (! $org) return null;

            $plan = $org->plan; // belongsTo Plan
            return $plan?->name;
        } catch (\Throwable $e) {
            Log::warning('[ROUTER] Failed to resolve plan', ['post_id' => $post->id, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /** Mappa tier logico → params OpenAI + costo stimato. */
    private function resolveTier(string $tier, string $plan, bool $heroOverride): array
    {
        return match ($tier) {
            self::TIER_PREMIUM => [
                'tier'           => $tier,
                'plan'           => $plan,
                'openai_model'   => 'gpt-image-1',
                'quality'        => 'high',
                'estimated_cost' => 0.167,
                'hero_override'  => $heroOverride,
            ],
            self::TIER_HIGH => [
                'tier'           => $tier,
                'plan'           => $plan,
                'openai_model'   => 'gpt-image-1',
                'quality'        => 'medium',
                'estimated_cost' => 0.040,
                'hero_override'  => $heroOverride,
            ],
            self::TIER_MEDIUM => [
                'tier'           => $tier,
                'plan'           => $plan,
                'openai_model'   => 'gpt-image-1-mini',
                'quality'        => 'medium',
                'estimated_cost' => 0.011,
                'hero_override'  => $heroOverride,
            ],
        };
    }

    /** Comportamento legacy quando il flag è OFF. */
    private function legacyDefault(): array
    {
        return [
            'tier'           => 'legacy',
            'plan'           => 'unknown',
            'openai_model'   => (string) config('services.openai.image_default_model', 'gpt-image-1'),
            'quality'        => 'high',
            'estimated_cost' => 0.167,
            'hero_override'  => false,
        ];
    }
}
