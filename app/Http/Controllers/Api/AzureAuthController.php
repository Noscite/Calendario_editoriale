<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Organization\Models\Organization;
use App\Domain\User\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Azure AD (Microsoft Entra ID) OAuth Authentication.
 *
 * Replica di azure_auth.py (Python/MSAL) — senza la libreria MSAL,
 * implementa il flusso OAuth 2.0 authorization code direttamente via HTTP.
 *
 * Routes:
 *   GET /api/auth/azure/login    → redirect a Microsoft login
 *   GET /api/auth/azure/callback → scambia code, trova/crea utente, emette Sanctum token
 */
final class AzureAuthController extends Controller
{
    private function clientId(): string
    {
        return (string) config('services.azure.client_id', '');
    }

    private function clientSecret(): string
    {
        return (string) config('services.azure.client_secret', '');
    }

    private function tenantId(): string
    {
        return (string) config('services.azure.tenant_id', 'common');
    }

    private function redirectUri(): string
    {
        return config('services.azure.redirect_uri',
            rtrim(config('app.url'), '/') . '/api/auth/azure/callback'
        );
    }

    private function frontendUrl(): string
    {
        return rtrim(config('services.frontend_url', 'https://calendar.noscite.it'), '/');
    }

    // GET /api/auth/azure/login
    public function login(): RedirectResponse
    {
        if (! $this->clientId()) {
            abort(501, 'Azure AD non configurato');
        }

        $state = Str::random(40);
        session(['azure_oauth_state' => $state]);

        $authUrl = 'https://login.microsoftonline.com/' . $this->tenantId() . '/oauth2/v2.0/authorize?' . http_build_query([
            'client_id'     => $this->clientId(),
            'response_type' => 'code',
            'redirect_uri'  => $this->redirectUri(),
            'scope'         => 'openid profile email User.Read',
            'state'         => $state,
            'response_mode' => 'query',
        ]);

        return redirect($authUrl);
    }

    // GET /api/auth/azure/callback
    public function callback(Request $request): RedirectResponse
    {
        $frontend = $this->frontendUrl();

        $error = $request->query('error');
        if ($error) {
            Log::error('Azure AD error', ['error' => $error, 'description' => $request->query('error_description')]);
            return redirect("{$frontend}/login?error=azure_denied");
        }

        $code = $request->query('code');
        if (! $code) {
            return redirect("{$frontend}/login?error=no_code");
        }

        try {
            // Scambia code per token (equivalente a msal.acquire_token_by_authorization_code)
            $tokenResponse = Http::asForm()->post(
                'https://login.microsoftonline.com/' . $this->tenantId() . '/oauth2/v2.0/token',
                [
                    'client_id'     => $this->clientId(),
                    'client_secret' => $this->clientSecret(),
                    'grant_type'    => 'authorization_code',
                    'code'          => $code,
                    'redirect_uri'  => $this->redirectUri(),
                    'scope'         => 'openid profile email User.Read',
                ]
            );

            $tokenData = $tokenResponse->json();

            if (isset($tokenData['error'])) {
                $desc = $tokenData['error_description'] ?? $tokenData['error'];
                Log::error('Azure AD token error', ['data' => $tokenData]);
                return redirect("{$frontend}/login?error=azure_token_failed");
            }

            $accessToken = $tokenData['access_token'];

            // Chiama Microsoft Graph per ottenere info utente
            $graphResponse = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->get('https://graph.microsoft.com/v1.0/me');

            if ($graphResponse->failed()) {
                Log::error('Azure Graph API error', ['status' => $graphResponse->status(), 'body' => $graphResponse->body()]);
                return redirect("{$frontend}/login?error=graph_failed");
            }

            $userInfo = $graphResponse->json();

            $azureId     = $userInfo['id'] ?? null;
            $email       = strtolower($userInfo['mail'] ?? $userInfo['userPrincipalName'] ?? '');
            $displayName = $userInfo['displayName'] ?? '';

            if (! $email) {
                return redirect("{$frontend}/login?error=no_email");
            }

            // Trova o crea utente (come Python: prima per azure_id, poi per email)
            $user = null;

            if ($azureId) {
                $user = User::where('azure_id', $azureId)->first();
            }

            if (! $user) {
                $user = User::where('email', $email)->first();

                if ($user) {
                    // Collega azure_id all'utente esistente
                    $user->azure_id      = $azureId;
                    $user->auth_provider = 'azure';
                    if (! $user->full_name && $displayName) {
                        $user->full_name = $displayName;
                    }
                    $user->save();
                    Log::info("Azure AD collegato a utente esistente: {$email}");
                } else {
                    // Crea nuova organizzazione + utente (come Python)
                    $orgName = $displayName ?: explode('@', $email)[0];
                    $slug    = Str::slug(explode('@', $email)[0], '-');
                    $slug    = substr($slug, 0, 50);

                    $org = Organization::create([
                        'name'                => $orgName,
                        'slug'                => $slug . '-' . Str::random(4),
                        'email'               => $email,
                        'subscription_status' => 'trial',
                    ]);

                    $user = User::create([
                        'email'         => $email,
                        'full_name'     => $displayName,
                        'azure_id'      => $azureId,
                        'auth_provider' => 'azure',
                        'role'          => 'admin',
                        'organization_id' => $org->id,
                        'is_active'     => true,
                    ]);

                    Log::info("Nuovo utente Azure AD creato: {$email}");
                }
            }

            if (! $user->is_active) {
                return redirect("{$frontend}/login?error=account_disabled");
            }

            // Emette Sanctum token (come Python: create_access_token)
            $sanctumToken = $user->createToken('azure')->plainTextToken;

            Log::info("Azure AD login OK", ['email' => $email, 'user_id' => $user->id]);

            return redirect("{$frontend}/login?azure_token={$sanctumToken}");
        } catch (\Throwable $e) {
            Log::error('Azure AD callback exception', ['error' => $e->getMessage()]);
            return redirect("{$frontend}/login?error=azure_exception");
        }
    }
}
