<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Brand\Models\Brand;
use App\Domain\Organization\Models\Organization;
use App\Domain\Subscription\Models\Plan;
use App\Domain\Subscription\Models\UsageLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica limiti del piano di sottoscrizione prima di azioni consumabili.
 *
 * Corrisponde a subscriptions.py → get_effective_limits() (Python):
 *   1. Controlla custom_limits dell'organizzazione (Enterprise)
 *   2. Fallback ai limiti del piano
 *   3. Default sicuri se nessun piano
 *
 * Uso nelle rotte:
 *   ->middleware('check.limits:brands')      — prima di creare un brand
 *   ->middleware('check.limits:calendars')    — prima di generare un calendario
 *   ->middleware('check.limits:images')       — prima di generare immagini
 *   ->middleware('check.limits:tokens')       — prima di usare token AI
 *
 * Se il limite è -1 → illimitato (Enterprise).
 * Se non c'è usage record per il periodo → lo crea con contatori a 0.
 */
class CheckSubscriptionLimits
{
    /**
     * @param  string|null  $resource  Tipo risorsa: brands, calendars, images, tokens
     */
    public function handle(Request $request, Closure $next, ?string $resource = null): Response
    {
        $user = $request->user();

        if (! $user || ! $user->organization_id) {
            // Lascia che EnsureOrganization gestisca questo caso
            return $next($request);
        }

        $org = $request->attributes->get('organization')
            ?? Organization::find($user->organization_id);

        if (! $org) {
            return $next($request);
        }

        $plan   = $org->plan_id ? Plan::find($org->plan_id) : null;
        $limits = $this->getEffectiveLimits($org, $plan);

        // Se nessuna risorsa specifica → solo inietta limiti nel request
        if (! $resource) {
            $request->attributes->set('subscription_limits', $limits);
            return $next($request);
        }

        // Conteggio risorse usate
        $periodStart = now()->startOfMonth()->toDateString();
        $periodEnd   = now()->endOfMonth()->toDateString();

        $usage = UsageLog::firstOrCreate(
            [
                'organization_id' => $org->id,
                'period_start'    => $periodStart,
            ],
            [
                'period_end' => $periodEnd,
            ]
        );

        // Piano Unlimited con chiavi proprie: bypass token e immagini
        if ($plan?->has_own_api_keys && in_array($resource, ['tokens', 'images'])) {
            $request->attributes->set('subscription_limits', $limits);
            $request->attributes->set('usage_log', $usage);
            return $next($request);
        }

        $exceeded = match ($resource) {
            'brands'    => $this->checkBrandsLimit($org, $limits),
            'calendars' => $this->checkUsageLimit(
                $usage->calendar_generations_used ?? 0,
                $limits['monthly_calendars'],
                'generazioni calendario'
            ),
            'images'    => $this->checkUsageLimit(
                $usage->images_generated ?? 0,
                $limits['monthly_images'],
                'immagini'
            ),
            'tokens'    => $this->checkUsageLimit(
                $usage->text_tokens_used ?? 0,
                $limits['monthly_tokens'],
                'token testo'
            ),
            default     => null,
        };

        if ($exceeded) {
            return response()->json([
                'detail'   => $exceeded,
                'resource' => $resource,
                'limits'   => $limits,
            ], 429);
        }

        // Inietta limiti e usage nel request per uso successivo
        $request->attributes->set('subscription_limits', $limits);
        $request->attributes->set('usage_log', $usage);

        return $next($request);
    }

    // ── Calcolo limiti effettivi ────────────────────────────────

    /**
     * Stessa logica di get_effective_limits() in Python subscriptions.py.
     * Priorità: custom_limits Enterprise → piano → default.
     */
    private function getEffectiveLimits(Organization $org, ?Plan $plan): array
    {
        if ($org->custom_limits && is_array($org->custom_limits)) {
            return [
                'max_brands'        => $org->custom_limits['max_brands'] ?? ($plan?->max_brands ?? 1),
                'max_users'         => $org->custom_limits['max_users'] ?? ($plan?->max_users ?? 1),
                'monthly_calendars' => $org->custom_limits['monthly_calendar_generations'] ?? ($plan?->monthly_calendar_generations ?? 5),
                'monthly_tokens'    => $org->custom_limits['monthly_text_tokens'] ?? ($plan?->monthly_text_tokens ?? 100000),
                'monthly_images'    => $org->custom_limits['monthly_images'] ?? ($plan?->monthly_images ?? 15),
            ];
        }

        if ($plan) {
            return [
                'max_brands'        => $plan->max_brands,
                'max_users'         => $plan->max_users,
                'monthly_calendars' => $plan->monthly_calendar_generations,
                'monthly_tokens'    => $plan->monthly_text_tokens,
                'monthly_images'    => $plan->monthly_images,
            ];
        }

        // Default (trial / fallback)
        return [
            'max_brands'        => 1,
            'max_users'         => 1,
            'monthly_calendars' => 5,
            'monthly_tokens'    => 100000,
            'monthly_images'    => 15,
        ];
    }

    // ── Check individuali ──────────────────────────────────────

    /**
     * Verifica numero brands vs limite max_brands.
     */
    private function checkBrandsLimit(Organization $org, array $limits): ?string
    {
        $maxBrands = $limits['max_brands'];

        // -1 = illimitato
        if ($maxBrands === -1) {
            return null;
        }

        $currentCount = Brand::where('organization_id', $org->id)->count();

        if ($currentCount >= $maxBrands) {
            return "Limite brand raggiunto ({$currentCount}/{$maxBrands}). Aggiorna il piano per crearne altri.";
        }

        return null;
    }

    /**
     * Verifica un contatore di usage generico vs il suo limite mensile.
     */
    private function checkUsageLimit(int $used, int $limit, string $label): ?string
    {
        // -1 = illimitato
        if ($limit === -1) {
            return null;
        }

        if ($used >= $limit) {
            return "Limite {$label} raggiunto ({$used}/{$limit}). Aggiorna il piano per continuare.";
        }

        return null;
    }
}
