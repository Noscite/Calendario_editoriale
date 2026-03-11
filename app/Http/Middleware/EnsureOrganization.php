<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Organization\Enums\OrganizationStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica che l'utente autenticato abbia un organization_id valido.
 *
 * Corrisponde ai check Python sparsi in brands.py, projects.py, ecc.:
 *   if not current_user.organization_id:
 *       raise HTTPException(status_code=400, detail="User has no organization")
 *
 * Uso nelle rotte:
 *   ->middleware('ensure.organization')
 *
 * Può essere applicato a tutto il gruppo protetto o a singoli prefix
 * (brands, projects, posts, generate, documents, …).
 */
class EnsureOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['detail' => 'Autenticazione richiesta'], 401);
        }

        if (! $user->organization_id) {
            return response()->json([
                'detail' => 'User has no organization',
            ], 400);
        }

        // Verifica che l'organizzazione sia attiva
        $organization = $user->organization;

        if (! $organization) {
            return response()->json([
                'detail' => 'Organizzazione non trovata',
            ], 400);
        }

        if (! $organization->is_active) {
            return response()->json([
                'detail' => 'Organizzazione disattivata. Contatta il supporto.',
            ], 403);
        }

        // Check trial scaduto
        if (
            $organization->subscription_status === OrganizationStatus::Trial
            && $organization->trial_ends_at !== null
            && $organization->trial_ends_at->isPast()
        ) {
            return response()->json([
                'detail'      => 'Il periodo di prova è scaduto.',
                'code'        => 'TRIAL_EXPIRED',
                'upgrade_url' => config('services.frontend_url') . '/settings/upgrade',
            ], 402);
        }

        // Rendi l'organizzazione disponibile nel request per evitare query duplicate
        $request->attributes->set('organization', $organization);

        return $next($request);
    }
}
