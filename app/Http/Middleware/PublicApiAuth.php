<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Organization\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autenticazione API pubblica via X-API-Key + verifica scopes.
 *
 * Corrisponde a api_key_auth.py (Python):
 *   get_api_key_user()  → autentica e ritorna (user, api_key)
 *   require_scope()     → factory che verifica scope
 *
 * Flusso:
 *   1. Legge header X-API-Key
 *   2. SHA-256 hash → lookup su api_keys.key_hash
 *   3. Verifica is_active, expires_at
 *   4. Verifica scope richiesto (se passato come parametro)
 *   5. Risolve l'utente associato (api_key.user_id)
 *   6. Aggiorna last_used_at
 *   7. Inietta user e api_key nel Request
 *
 * Uso nelle rotte:
 *   ->middleware('api.key')           // solo auth, nessuno scope
 *   ->middleware('api.key:read')      // require scope "read"
 *   ->middleware('api.key:write')     // require scope "write"
 *   ->middleware('api.key:publish')   // require scope "publish"
 *
 * Scope "admin" bypassa qualsiasi controllo scope (come Python).
 */
class PublicApiAuth
{
    public function handle(Request $request, Closure $next, ?string $requiredScope = null): Response
    {
        // ── 1. Leggi header ────────────────────────────────────
        $rawKey = $request->header('X-API-Key');

        if (! $rawKey) {
            return response()->json([
                'detail' => 'API Key richiesta. Usa header X-API-Key',
            ], 401);
        }

        // ── 2. Lookup via hash ─────────────────────────────────
        $keyHash = ApiKey::hashKey($rawKey);

        $apiKey = ApiKey::where('key_hash', $keyHash)
            ->where('is_active', true)
            ->first();

        if (! $apiKey) {
            return response()->json(['detail' => 'API Key non valida'], 401);
        }

        // ── 3. Scadenza ────────────────────────────────────────
        if ($apiKey->expires_at && $apiKey->expires_at->isPast()) {
            return response()->json(['detail' => 'API Key scaduta'], 401);
        }

        // ── 4. Scope check ─────────────────────────────────────
        if ($requiredScope && ! $apiKey->hasScope($requiredScope)) {
            return response()->json([
                'detail' => "Scope '{$requiredScope}' richiesto. La tua API key ha: "
                    . implode(', ', $apiKey->scopes ?? []),
            ], 403);
        }

        // ── 5. Risolvi utente ──────────────────────────────────
        $user = $apiKey->user;

        if (! $user) {
            return response()->json(['detail' => 'Utente associato non trovato'], 401);
        }

        // ── 6. Aggiorna last_used_at ───────────────────────────
        $apiKey->timestamps = false;
        $apiKey->update(['last_used_at' => now()]);

        // ── 7. Inietta nel request ─────────────────────────────
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('api_key', $apiKey);

        return $next($request);
    }
}
