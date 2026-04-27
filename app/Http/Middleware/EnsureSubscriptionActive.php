<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionActive
{
    // Prefissi URI che possono sempre passare anche senza subscription attiva
    private const ALLOWED_PREFIXES = [
        'admin',          // Filament admin panel
        'livewire',       // Filament Livewire calls
        'api/auth',       // login/logout/profile
        'api/health',
        'api/help',
        'api/webhooks',
        'api/subscriptions/plans', // pagine pubbliche piani
        'subscription',   // la pagina /subscription/inactive stessa
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Utenti non autenticati → lasciamo passare (auth:sanctum blocca prima)
        if (! $request->user()) {
            return $next($request);
        }

        // Whitelist URI
        $path = ltrim($request->path(), '/');
        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return $next($request);
            }
        }

        $user = $request->user();
        $org  = $user->organization;

        // Nessuna organizzazione → lasciamo passare, il controller gestirà il caso
        if (! $org) {
            return $next($request);
        }

        // System tenant bypassa tutto
        if ($org->is_system_tenant) {
            return $next($request);
        }

        // Carica la subscription (nullable: org legacy potrebbero non avere un record)
        $sub = $org->subscription;

        if ($sub) {
            // Subscription record presente → usa la state machine
            if (! $sub->canAccessApp()) {
                return $this->denyAccess($request);
            }
        } else {
            // Fallback legacy: controlla subscription_status sulla tabella organizations
            $legacyActive = in_array(
                $org->subscription_status?->value,
                ['active', 'trial'],
                strict: true,
            );
            if (! $legacyActive) {
                return $this->denyAccess($request);
            }
        }

        return $next($request);
    }

    private function denyAccess(Request $request): Response
    {
        if ($request->expectsJson() || str_starts_with($request->path(), 'api/')) {
            return response()->json([
                'error' => [
                    'code'    => 'SUBSCRIPTION_INACTIVE',
                    'message' => 'Il tuo abbonamento non è attivo. Accedi al pannello per maggiori informazioni.',
                ],
            ], 402);
        }

        return redirect()->to('/subscription/inactive');
    }
}
