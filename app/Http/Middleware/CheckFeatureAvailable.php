<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Subscription\Models\Subscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocca feature disabilitate durante il trial.
 *
 * Delega a Subscription::canUseFeature() che legge
 * config('trial.features_disabled_during_trial').
 *
 * Uso:
 *   ->middleware('check.feature:social_auto_publish')
 *   ->middleware('check.feature:social_account_connect')
 *
 * In caso di feature non disponibile risponde con 403 e payload
 * `error.code === 'FEATURE_DISABLED_DURING_TRIAL'` riconosciuto dal
 * frontend per triggerare il modal "feature non disponibile".
 */
class CheckFeatureAvailable
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if (! $user || ! $user->organization_id) {
            return $next($request);
        }

        $org = $user->organization;

        if (! $org || $org->is_system_tenant) {
            return $next($request);
        }

        $sub = $org->subscription;

        if ($sub instanceof Subscription && ! $sub->canUseFeature($feature)) {
            return response()->json([
                'error' => [
                    'code'    => 'FEATURE_DISABLED_DURING_TRIAL',
                    'feature' => $feature,
                    'message' => 'Questa funzionalità è disponibile dopo l\'attivazione del piano.',
                ],
            ], 403);
        }

        return $next($request);
    }
}
