<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Brand\Models\Brand;
use App\Domain\Social\Models\SocialConnection;
use App\Domain\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Connessioni social e OAuth — replica esatta di social.py (Python 812 righe).
 *
 * Flusso OAuth completo per Facebook, Instagram, LinkedIn, Google Business.
 * State gestito via Redis/Cache (in Python: dict in-memory, commento "in produzione usare Redis").
 *
 * redirect_uri DEVONO corrispondere ESATTAMENTE a quelli registrati nelle app OAuth:
 *   Facebook:  {BASE_URL}/api/social/callback/facebook
 *   Instagram: {BASE_URL}/api/social/callback/instagram
 *   LinkedIn:  {BASE_URL}/api/social/callback/linkedin
 *   Google:    {BASE_URL}/api/social/callback/google
 */
final class SocialController extends Controller
{
    /** TTL per state e pending selections (15 minuti) */
    private const STATE_TTL = 900;

    /** Facebook Graph API version (come Python: v18.0) */
    private const FB_API_VERSION = 'v18.0';

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    private function frontendUrl(): string
    {
        return config('services.frontend_url', 'https://calendar.noscite.it');
    }

    private function baseUrl(): string
    {
        return rtrim(config('app.url'), '/');
    }

    private function metaAppId(): string
    {
        return config('services.meta.app_id');
    }

    private function metaAppSecret(): string
    {
        return config('services.meta.app_secret');
    }

    private function linkedinClientId(): string
    {
        return config('services.linkedin.client_id');
    }

    private function linkedinClientSecret(): string
    {
        return config('services.linkedin.client_secret');
    }

    private function googleClientId(): string
    {
        return config('services.google.client_id');
    }

    private function googleClientSecret(): string
    {
        return config('services.google.client_secret');
    }

    // ═════════════════════════════════════════════════════════════
    // GET /api/social/connections/{brand_id}
    // Replica: get_brand_connections()
    // ═════════════════════════════════════════════════════════════

    public function connections(int $brandId, Request $request): JsonResponse
    {
        $brand = Brand::where('id', $brandId)
            ->where('organization_id', $request->user()->organization_id)
            ->first();

        if (!$brand) {
            return response()->json(['detail' => 'Brand non trovato'], 404);
        }

        $connections = SocialConnection::where('brand_id', $brandId)
            ->where('is_active', true)
            ->get()
            ->map(fn (SocialConnection $c) => [
                'id'                    => $c->id,
                'platform'              => $c->platform->value,
                'external_account_id'   => $c->external_account_id,
                'external_account_name' => $c->external_account_name,
                'external_account_url'  => $c->external_account_url,
                'account_type'          => $c->account_type,
                'is_active'             => $c->is_active,
                'connected_at'          => $c->connected_at?->toIso8601String(),
            ]);

        return response()->json($connections);
    }

    // ═════════════════════════════════════════════════════════════
    // GET /api/social/authorize/{platform}?brand_id=&token=
    // Replica: authorize_social()  — genera state, salva in cache, redirect
    // ═════════════════════════════════════════════════════════════

    public function authorize(string $platform, Request $request): RedirectResponse|JsonResponse
    {
        $brandId = (int) $request->query('brand_id');
        $token = $request->query('token');

        // Autentica da token query param (come Python: jwt.decode)
        if (!$token) {
            return response()->json(['detail' => 'Token richiesto'], 401);
        }

        try {
            $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
            if (!$personalAccessToken) {
                return response()->json(['detail' => 'Token non valido'], 401);
            }
            /** @var User $currentUser */
            $currentUser = $personalAccessToken->tokenable;
        } catch (\Throwable $e) {
            return response()->json(['detail' => 'Token non valido'], 401);
        }

        // Verifica brand (come Python: brand.organization_id == current_user.organization_id)
        $brand = Brand::where('id', $brandId)
            ->where('organization_id', $currentUser->organization_id)
            ->first();

        if (!$brand) {
            return response()->json(['detail' => 'Brand non trovato'], 404);
        }

        // Genera state token (come Python: secrets.token_urlsafe(32))
        $state = Str::random(43); // ~32 bytes urlsafe base64

        // Salva state in cache (come Python: oauth_states[state] = {...})
        Cache::put("oauth_state:{$state}", [
            'brand_id'   => $brandId,
            'user_id'    => $currentUser->id,
            'platform'   => $platform,
            'created_at' => now()->toIso8601String(),
        ], self::STATE_TTL);

        // Costruisci URL di autorizzazione — parametri ESATTI dal Python
        $authUrl = match ($platform) {
            'facebook' => $this->buildFacebookAuthUrl($state),
            'instagram' => $this->buildInstagramAuthUrl($state),
            'linkedin' => $this->buildLinkedinAuthUrl($state),
            'google_business' => $this->buildGoogleAuthUrl($state),
            default => null,
        };

        if (!$authUrl) {
            return response()->json(['detail' => 'Piattaforma non supportata'], 400);
        }

        return redirect($authUrl);
    }

    private function buildFacebookAuthUrl(string $state): string
    {
        // Parametri ESATTI dal Python
        return 'https://www.facebook.com/' . self::FB_API_VERSION . '/dialog/oauth?' . http_build_query([
            'client_id'     => $this->metaAppId(),
            'redirect_uri'  => $this->baseUrl() . '/api/social/callback/facebook',
            'state'         => $state,
            'scope'         => 'pages_show_list,pages_read_engagement,pages_manage_posts,read_insights,public_profile',
            'response_type' => 'code',
        ]);
    }

    private function buildInstagramAuthUrl(string $state): string
    {
        // Parametri ESATTI dal Python — scope include instagram_* + pages + business_management
        return 'https://www.facebook.com/' . self::FB_API_VERSION . '/dialog/oauth?' . http_build_query([
            'client_id'     => $this->metaAppId(),
            'redirect_uri'  => $this->baseUrl() . '/api/social/callback/instagram',
            'state'         => $state,
            'scope'         => 'instagram_basic,instagram_content_publish,instagram_manage_insights,pages_show_list,pages_read_engagement,pages_manage_posts,business_management',
            'response_type' => 'code',
        ]);
    }

    private function buildLinkedinAuthUrl(string $state): string
    {
        // Parametri ESATTI dal Python
        return 'https://www.linkedin.com/oauth/v2/authorization?' . http_build_query([
            'response_type' => 'code',
            'client_id'     => $this->linkedinClientId(),
            'redirect_uri'  => $this->baseUrl() . '/api/social/callback/linkedin',
            'state'         => $state,
            'scope'         => 'openid profile email w_member_social w_organization_social r_organization_admin',
        ]);
    }

    private function buildGoogleAuthUrl(string $state): string
    {
        // Parametri ESATTI dal Python — include access_type=offline, prompt=consent
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id'     => $this->googleClientId(),
            'redirect_uri'  => $this->baseUrl() . '/api/social/callback/google',
            'state'         => $state,
            'scope'         => 'https://www.googleapis.com/auth/business.manage',
            'response_type' => 'code',
            'access_type'   => 'offline',
            'prompt'        => 'consent',
        ]);
    }

    // ═════════════════════════════════════════════════════════════
    // GET /api/social/callback/facebook
    // Replica: facebook_callback() — exchange code, get pages, save
    // ═════════════════════════════════════════════════════════════

    public function callbackFacebook(Request $request): RedirectResponse
    {
        $code = $request->query('code');
        $state = $request->query('state');
        $error = $request->query('error');
        $frontend = $this->frontendUrl();

        if ($error || !$code || !$state) {
            return redirect("{$frontend}?social_error=" . ($error ?: 'missing_code'));
        }

        // Recupera e invalida state (come Python: oauth_states.pop(state, None))
        $stateData = Cache::pull("oauth_state:{$state}");
        if (!$stateData) {
            return redirect("{$frontend}?social_error=invalid_state");
        }

        try {
            // Scambia code per access_token (come Python)
            $tokenResponse = Http::get("https://graph.facebook.com/" . self::FB_API_VERSION . "/oauth/access_token", [
                'client_id'     => $this->metaAppId(),
                'client_secret' => $this->metaAppSecret(),
                'redirect_uri'  => $this->baseUrl() . '/api/social/callback/facebook',
                'code'          => $code,
            ]);
            $tokenData = $tokenResponse->json();

            if (isset($tokenData['error'])) {
                $msg = $tokenData['error']['message'] ?? 'unknown';
                return redirect("{$frontend}?social_error={$msg}");
            }

            $accessToken = $tokenData['access_token'];

            // Ottieni pagine gestite (come Python: /me/accounts)
            $pagesResponse = Http::get("https://graph.facebook.com/" . self::FB_API_VERSION . "/me/accounts", [
                'access_token' => $accessToken,
            ]);
            $pagesData = $pagesResponse->json();
            $pages = $pagesData['data'] ?? [];

            Log::info("Facebook pages received", ['count' => count($pages)]);

            $brandId = $stateData['brand_id'];
            $userId = $stateData['user_id'];

            // Nessuna pagina — usa profilo (come Python)
            if (empty($pages)) {
                $meResponse = Http::get("https://graph.facebook.com/" . self::FB_API_VERSION . "/me", [
                    'access_token' => $accessToken,
                    'fields'       => 'id,name',
                ]);
                $meData = $meResponse->json();

                $this->saveFacebookConnection($brandId, $userId, [
                    'access_token' => $accessToken,
                    'id'           => $meData['id'],
                    'name'         => $meData['name'],
                    'account_type' => 'profile',
                ]);

                return redirect("{$frontend}/?social_connected=facebook");
            }

            // Una sola pagina — salva direttamente (come Python)
            if (count($pages) === 1) {
                $page = $pages[0];
                $this->saveFacebookConnection($brandId, $userId, [
                    'access_token' => $page['access_token'],
                    'id'           => $page['id'],
                    'name'         => $page['name'],
                    'account_type' => 'page',
                ]);

                return redirect("{$frontend}/?social_connected=facebook");
            }

            // Più pagine — salva temporaneamente per selezione (come Python: pending_facebook_pages)
            $selectionToken = Str::random(43);
            Cache::put("pending_fb_pages:{$selectionToken}", [
                'brand_id' => $brandId,
                'user_id'  => $userId,
                'pages'    => collect($pages)->map(fn ($p) => [
                    'id'           => $p['id'],
                    'name'         => $p['name'],
                    'access_token' => $p['access_token'],
                ])->all(),
            ], self::STATE_TTL);

            return redirect("{$frontend}/select-facebook-page?token={$selectionToken}");
        } catch (\Throwable $e) {
            Log::error("Facebook callback error", ['error' => $e->getMessage()]);
            return redirect("{$frontend}?social_error=" . urlencode($e->getMessage()));
        }
    }

    /**
     * Helper: salva connessione Facebook (elimina vecchia, crea nuova).
     * Pattern identico usato 3 volte in Python.
     */
    private function saveFacebookConnection(int $brandId, int $userId, array $data): void
    {
        SocialConnection::where('brand_id', $brandId)
            ->where('platform', 'facebook')
            ->delete();

        SocialConnection::create([
            'brand_id'              => $brandId,
            'platform'              => 'facebook',
            'access_token'          => $data['access_token'],
            'external_account_id'   => $data['id'],
            'external_account_name' => $data['name'],
            'external_account_url'  => "https://facebook.com/{$data['id']}",
            'account_type'          => $data['account_type'],
            'connected_by_user_id'  => $userId,
            'is_active'             => true,
        ]);
    }

    // ═════════════════════════════════════════════════════════════
    // GET /api/social/callback/instagram
    // Replica: instagram_callback() — exchange, get pages, find IG business account
    // ═════════════════════════════════════════════════════════════

    public function callbackInstagram(Request $request): RedirectResponse
    {
        $code = $request->query('code');
        $state = $request->query('state');
        $error = $request->query('error');
        $frontend = $this->frontendUrl();

        if ($error || !$code || !$state) {
            return redirect("{$frontend}?social_error=" . ($error ?: 'missing_code'));
        }

        $stateData = Cache::pull("oauth_state:{$state}");
        if (!$stateData) {
            return redirect("{$frontend}?social_error=invalid_state");
        }

        try {
            // Scambia code per access_token — USA IL REDIRECT_URI DI INSTAGRAM! (come Python)
            $tokenResponse = Http::get("https://graph.facebook.com/" . self::FB_API_VERSION . "/oauth/access_token", [
                'client_id'     => $this->metaAppId(),
                'client_secret' => $this->metaAppSecret(),
                'redirect_uri'  => $this->baseUrl() . '/api/social/callback/instagram',
                'code'          => $code,
            ]);
            $tokenData = $tokenResponse->json();

            if (isset($tokenData['error'])) {
                Log::error("Instagram token error", ['data' => $tokenData]);
                $msg = $tokenData['error']['message'] ?? 'token_error';
                return redirect("{$frontend}?social_error={$msg}");
            }

            $accessToken = $tokenData['access_token'];

            // Ottieni pagine Facebook collegate (come Python)
            $pagesResponse = Http::get("https://graph.facebook.com/" . self::FB_API_VERSION . "/me/accounts", [
                'access_token' => $accessToken,
            ]);
            $pagesData = $pagesResponse->json();
            $pages = $pagesData['data'] ?? [];

            Log::info("Instagram - Pages found", ['count' => count($pages)]);

            if (empty($pages)) {
                Log::error("Instagram - No Facebook pages found");
                return redirect("{$frontend}?social_error=no_pages_found");
            }

            // Per ogni pagina, cerca account Instagram collegato (come Python: loop pagine)
            $instagramAccount = null;
            $pageAccessToken = null;

            foreach ($pages as $page) {
                Log::info("Instagram - Checking page", ['name' => $page['name'] ?? 'unknown', 'id' => $page['id']]);

                $igResponse = Http::get("https://graph.facebook.com/" . self::FB_API_VERSION . "/{$page['id']}", [
                    'access_token' => $page['access_token'],
                    'fields'       => 'instagram_business_account',
                ]);
                $igData = $igResponse->json();

                Log::info("Instagram - Page IG data", ['data' => $igData]);

                if (isset($igData['instagram_business_account']['id'])) {
                    $instagramAccount = $igData['instagram_business_account']['id'];
                    $pageAccessToken = $page['access_token'];
                    Log::info("Instagram - Found IG business account", ['ig_id' => $instagramAccount]);
                    break;
                }
            }

            if (!$instagramAccount) {
                Log::error("Instagram - No Instagram business account linked to any Facebook page");
                return redirect("{$frontend}?social_error=no_instagram_business_account");
            }

            // Ottieni info account Instagram (come Python: fields=id,username)
            $igInfoResponse = Http::get("https://graph.facebook.com/" . self::FB_API_VERSION . "/{$instagramAccount}", [
                'access_token' => $pageAccessToken,
                'fields'       => 'id,username',
            ]);
            $igInfo = $igInfoResponse->json();

            $brandId = $stateData['brand_id'];
            $userId = $stateData['user_id'];

            // Rimuovi connessioni Instagram esistenti e salva nuova (come Python)
            SocialConnection::where('brand_id', $brandId)
                ->where('platform', 'instagram')
                ->delete();

            SocialConnection::create([
                'brand_id'              => $brandId,
                'connected_by_user_id'  => $userId,
                'platform'              => 'instagram',
                'external_account_id'   => $instagramAccount,
                'access_token'          => $pageAccessToken,
                'account_type'          => 'business',
                'external_account_name' => $igInfo['username'] ?? 'Instagram Business',
                'external_account_url'  => 'https://instagram.com/' . ($igInfo['username'] ?? ''),
                'is_active'             => true,
            ]);

            Log::info("Instagram connected", ['username' => $igInfo['username'] ?? '', 'brand_id' => $brandId]);

            return redirect("{$frontend}/?social_connected=instagram");
        } catch (\Throwable $e) {
            Log::error("Instagram callback error", ['error' => $e->getMessage()]);
            return redirect("{$frontend}?social_error=callback_failed");
        }
    }

    // ═════════════════════════════════════════════════════════════
    // GET /api/social/callback/linkedin
    // Replica: linkedin_callback() — exchange, get profile + orgs, save
    // ═════════════════════════════════════════════════════════════

    public function callbackLinkedin(Request $request): RedirectResponse
    {
        $code = $request->query('code');
        $state = $request->query('state');
        $error = $request->query('error');
        $frontend = $this->frontendUrl();

        if ($error || !$code || !$state) {
            return redirect("{$frontend}?social_error=" . ($error ?: 'missing_code'));
        }

        $stateData = Cache::pull("oauth_state:{$state}");
        if (!$stateData) {
            return redirect("{$frontend}?social_error=invalid_state");
        }

        try {
            // Scambia code per token (come Python: POST application/x-www-form-urlencoded)
            $tokenResponse = Http::asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', [
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'client_id'     => $this->linkedinClientId(),
                'client_secret' => $this->linkedinClientSecret(),
                'redirect_uri'  => $this->baseUrl() . '/api/social/callback/linkedin',
            ]);
            $tokenData = $tokenResponse->json();

            if (isset($tokenData['error'])) {
                Log::error("LinkedIn token error", ['data' => $tokenData]);
                $msg = $tokenData['error_description'] ?? 'token_error';
                return redirect("{$frontend}?social_error={$msg}");
            }

            $accessToken = $tokenData['access_token'];
            $expiresIn = $tokenData['expires_in'] ?? 5184000; // 60 giorni default (come Python)

            // Ottieni info profilo (come Python: /v2/userinfo)
            $profileResponse = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->get('https://api.linkedin.com/v2/userinfo');
            $profileData = $profileResponse->json();

            Log::info("LinkedIn profile", ['name' => $profileData['name'] ?? '']);

            // Ottieni organizzazioni (pagine aziendali) — come Python: organizationAcls
            $orgsResponse = Http::withHeaders([
                'Authorization'            => "Bearer {$accessToken}",
                'X-Restli-Protocol-Version' => '2.0.0',
            ])->get('https://api.linkedin.com/v2/organizationAcls', [
                'q'          => 'roleAssignee',
                'role'       => 'ADMINISTRATOR',
                'projection' => '(elements*(organization~(id,localizedName,vanityName)))',
            ]);
            $orgsData = $orgsResponse->json();

            Log::info("LinkedIn organizations response", ['data' => $orgsData]);

            $organizations = [];
            foreach ($orgsData['elements'] ?? [] as $element) {
                $org = $element['organization~'] ?? [];
                if (!empty($org)) {
                    $organizations[] = [
                        'id'          => $org['id'] ?? null,
                        'name'        => $org['localizedName'] ?? null,
                        'vanity_name' => $org['vanityName'] ?? null,
                    ];
                }
            }

            Log::info("LinkedIn found organizations", ['count' => count($organizations)]);

            $brandId = $stateData['brand_id'];
            $userId = $stateData['user_id'];

            // Se ci sono organizzazioni, redirect a selezione (come Python)
            if (count($organizations) > 0) {
                $selectionToken = Str::random(43);
                Cache::put("pending_li_orgs:{$selectionToken}", [
                    'brand_id'      => $brandId,
                    'user_id'       => $userId,
                    'access_token'  => $accessToken,
                    'expires_in'    => $expiresIn,
                    'profile'       => [
                        'id'   => $profileData['sub'] ?? '',
                        'name' => $profileData['name'] ?? '',
                    ],
                    'organizations' => $organizations,
                ], self::STATE_TTL);

                return redirect("{$frontend}/select-linkedin-org?token={$selectionToken}");
            }

            // Nessuna organizzazione — salva solo profilo (come Python)
            SocialConnection::where('brand_id', $brandId)
                ->where('platform', 'linkedin')
                ->delete();

            SocialConnection::create([
                'brand_id'              => $brandId,
                'platform'              => 'linkedin',
                'access_token'          => $accessToken,
                'token_expires_at'      => now()->addSeconds($expiresIn),
                'external_account_id'   => $profileData['sub'] ?? '',
                'external_account_name' => ($profileData['name'] ?? '') . ' (Profilo)',
                'external_account_url'  => 'https://linkedin.com/in/' . ($profileData['sub'] ?? ''),
                'account_type'          => 'profile',
                'connected_by_user_id'  => $userId,
                'is_active'             => true,
            ]);

            Log::info("LinkedIn connected: profile only", ['brand_id' => $brandId]);

            return redirect("{$frontend}/?social_connected=linkedin");
        } catch (\Throwable $e) {
            Log::error("LinkedIn callback error", ['error' => $e->getMessage()]);
            return redirect("{$frontend}?social_error=" . urlencode($e->getMessage()));
        }
    }

    // ═════════════════════════════════════════════════════════════
    // GET /api/social/callback/google
    // Replica: google_callback() — exchange, get accounts + locations
    // ═════════════════════════════════════════════════════════════

    public function callbackGoogle(Request $request): RedirectResponse
    {
        $code = $request->query('code');
        $state = $request->query('state');
        $error = $request->query('error');
        $frontend = $this->frontendUrl();

        if ($error || !$code || !$state) {
            return redirect("{$frontend}?social_error=" . ($error ?: 'missing_code'));
        }

        $stateData = Cache::pull("oauth_state:{$state}");
        if (!$stateData) {
            return redirect("{$frontend}?social_error=invalid_state");
        }

        try {
            // Scambia code per token (come Python: POST a oauth2.googleapis.com/token)
            $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'code'          => $code,
                'client_id'     => $this->googleClientId(),
                'client_secret' => $this->googleClientSecret(),
                'redirect_uri'  => $this->baseUrl() . '/api/social/callback/google',
                'grant_type'    => 'authorization_code',
            ]);
            $tokenData = $tokenResponse->json();

            if (isset($tokenData['error'])) {
                $msg = $tokenData['error_description'] ?? 'token_error';
                return redirect("{$frontend}?social_error={$msg}");
            }

            $accessToken = $tokenData['access_token'];
            $refreshToken = $tokenData['refresh_token'] ?? null;
            $expiresIn = $tokenData['expires_in'] ?? 3600;

            // Ottieni accounts Google Business Profile (come Python)
            $accountsResponse = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->get('https://mybusinessaccountmanagement.googleapis.com/v1/accounts');
            $accountsData = $accountsResponse->json();

            Log::info("Google Business accounts", ['data' => $accountsData]);

            $accounts = $accountsData['accounts'] ?? [];
            if (empty($accounts)) {
                return redirect("{$frontend}?social_error=no_google_business_accounts");
            }

            // Prendi il primo account (come Python)
            $account = $accounts[0];
            $accountName = $account['name'];

            // Ottieni locations per questo account (come Python)
            $locationsResponse = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
            ])->get("https://mybusinessbusinessinformation.googleapis.com/v1/{$accountName}/locations", [
                'readMask' => 'name,title',
            ]);
            $locationsData = $locationsResponse->json();

            Log::info("Google Business locations", ['data' => $locationsData]);

            $locations = $locationsData['locations'] ?? [];

            if (empty($locations)) {
                return redirect("{$frontend}?social_error=no_google_business_locations");
            }

            $brandId = $stateData['brand_id'];
            $userId = $stateData['user_id'];

            // Una sola location — salva direttamente (come Python)
            if (count($locations) === 1) {
                $location = $locations[0];

                SocialConnection::where('brand_id', $brandId)
                    ->where('platform', 'google_business')
                    ->delete();

                SocialConnection::create([
                    'brand_id'              => $brandId,
                    'platform'              => 'google_business',
                    'access_token'          => $accessToken,
                    'refresh_token'         => $refreshToken,
                    'token_expires_at'      => now()->addSeconds($expiresIn),
                    'external_account_id'   => $location['name'],
                    'external_account_name' => $location['title'] ?? 'Google Business',
                    'account_type'          => 'location',
                    'connected_by_user_id'  => $userId,
                    'is_active'             => true,
                ]);

                return redirect("{$frontend}/brand/{$brandId}?social_connected=google_business");
            }

            // Più locations — salva per selezione (come Python: pending_google_locations)
            $selectionToken = Str::random(43);
            Cache::put("pending_google_locs:{$selectionToken}", [
                'brand_id'      => $brandId,
                'user_id'       => $userId,
                'access_token'  => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_in'    => $expiresIn,
                'locations'     => collect($locations)->map(fn ($loc) => [
                    'id'   => $loc['name'],
                    'name' => $loc['title'] ?? $loc['name'],
                ])->all(),
            ], self::STATE_TTL);

            return redirect("{$frontend}/select-google-location?token={$selectionToken}");
        } catch (\Throwable $e) {
            Log::error("Google Business callback error", ['error' => $e->getMessage()]);
            return redirect("{$frontend}?social_error=" . urlencode($e->getMessage()));
        }
    }

    // ═════════════════════════════════════════════════════════════
    // DELETE /api/social/disconnect/{connection_id}
    // Replica: disconnect_social()
    // ═════════════════════════════════════════════════════════════

    public function disconnect(int $connectionId, Request $request): JsonResponse
    {
        // Verifica appartenenza org (come Python: join Brand, filter organization_id)
        $connection = SocialConnection::whereHas('brand', function ($q) use ($request) {
            $q->where('organization_id', $request->user()->organization_id);
        })->find($connectionId);

        if (!$connection) {
            return response()->json(['detail' => 'Connessione non trovata'], 404);
        }

        $connection->update(['is_active' => false]);

        return response()->json(['message' => 'Account disconnesso']);
    }

    // ═════════════════════════════════════════════════════════════
    // Facebook page selection
    // Replica: get_facebook_pages() + select_facebook_page()
    // ═════════════════════════════════════════════════════════════

    // GET /api/social/facebook-pages/{token}
    public function facebookPages(string $token): JsonResponse
    {
        $data = Cache::get("pending_fb_pages:{$token}");

        if (!$data) {
            return response()->json(['detail' => 'Token non valido o scaduto'], 404);
        }

        // Restituisci solo id e name (come Python: senza access_token)
        $pages = collect($data['pages'])->map(fn ($p) => [
            'id'   => $p['id'],
            'name' => $p['name'],
        ])->all();

        return response()->json(['pages' => $pages]);
    }

    // POST /api/social/facebook-pages/{token}/select
    public function selectFacebookPage(string $token, Request $request): JsonResponse
    {
        $pageId = $request->input('page_id') ?? $request->query('page_id');

        // Recupera e invalida (come Python: pop)
        $data = Cache::pull("pending_fb_pages:{$token}");
        if (!$data) {
            return response()->json(['detail' => 'Token non valido o scaduto'], 404);
        }

        // Trova la pagina selezionata (come Python: loop)
        $selectedPage = null;
        foreach ($data['pages'] as $page) {
            if ($page['id'] === $pageId) {
                $selectedPage = $page;
                break;
            }
        }

        if (!$selectedPage) {
            return response()->json(['detail' => 'Pagina non trovata'], 400);
        }

        // Salva connessione (come Python)
        $this->saveFacebookConnection($data['brand_id'], $data['user_id'], [
            'access_token' => $selectedPage['access_token'],
            'id'           => $selectedPage['id'],
            'name'         => $selectedPage['name'],
            'account_type' => 'page',
        ]);

        return response()->json(['success' => true, 'page_name' => $selectedPage['name']]);
    }

    // ═════════════════════════════════════════════════════════════
    // LinkedIn org selection
    // Replica: get_linkedin_orgs() + select_linkedin_org()
    // ═════════════════════════════════════════════════════════════

    // GET /api/social/linkedin-orgs/{token}
    public function linkedinOrgs(string $token): JsonResponse
    {
        $data = Cache::get("pending_li_orgs:{$token}");

        if (!$data) {
            return response()->json(['detail' => 'Token non valido o scaduto'], 404);
        }

        // Come Python: restituisci profile + organizations
        return response()->json([
            'profile'       => $data['profile'],
            'organizations' => $data['organizations'],
        ]);
    }

    // POST /api/social/linkedin-orgs/{token}/select
    public function selectLinkedinOrg(string $token, Request $request): JsonResponse
    {
        $orgId = $request->input('org_id') ?? $request->query('org_id');
        $includeProfile = filter_var(
            $request->input('include_profile', $request->query('include_profile', false)),
            FILTER_VALIDATE_BOOLEAN
        );

        $data = Cache::pull("pending_li_orgs:{$token}");
        if (!$data) {
            return response()->json(['detail' => 'Token non valido o scaduto'], 404);
        }

        $brandId = $data['brand_id'];
        $userId = $data['user_id'];
        $accessToken = $data['access_token'];
        $expiresIn = $data['expires_in'];

        // Elimina vecchie connessioni LinkedIn per questo brand (come Python)
        SocialConnection::where('brand_id', $brandId)
            ->where('platform', 'linkedin')
            ->delete();

        // Se richiesto, salva anche il profilo personale (come Python)
        if ($includeProfile && !empty($data['profile'])) {
            $profile = $data['profile'];
            SocialConnection::create([
                'brand_id'              => $brandId,
                'platform'              => 'linkedin',
                'access_token'          => $accessToken,
                'token_expires_at'      => now()->addSeconds($expiresIn),
                'external_account_id'   => $profile['id'],
                'external_account_name' => "{$profile['name']} (Profilo)",
                'external_account_url'  => "https://linkedin.com/in/{$profile['id']}",
                'account_type'          => 'profile',
                'connected_by_user_id'  => $userId,
                'is_active'             => ($orgId === 'profile'), // Attivo se solo profilo (come Python)
            ]);
        }

        // Se solo profilo personale, ritorna subito (come Python)
        if ($orgId === 'profile') {
            return response()->json([
                'success'  => true,
                'org_name' => ($data['profile']['name'] ?? '') . ' (Profilo)',
            ]);
        }

        // Trova l'organizzazione selezionata (come Python: loop)
        $selectedOrg = null;
        foreach ($data['organizations'] as $org) {
            if ((string) $org['id'] === $orgId) {
                $selectedOrg = $org;
                break;
            }
        }

        if (!$selectedOrg) {
            return response()->json(['detail' => 'Organizzazione non trovata'], 404);
        }

        // Salva organizzazione (come Python)
        SocialConnection::create([
            'brand_id'              => $brandId,
            'platform'              => 'linkedin',
            'access_token'          => $accessToken,
            'token_expires_at'      => now()->addSeconds($expiresIn),
            'external_account_id'   => (string) $selectedOrg['id'],
            'external_account_name' => "{$selectedOrg['name']} (Pagina)",
            'external_account_url'  => 'https://linkedin.com/company/' . ($selectedOrg['vanity_name'] ?? $selectedOrg['id']),
            'account_type'          => 'organization',
            'connected_by_user_id'  => $userId,
            'is_active'             => true,
        ]);

        return response()->json(['success' => true, 'org_name' => $selectedOrg['name']]);
    }

    // ═════════════════════════════════════════════════════════════
    // Google location selection
    // Replica: get_google_locations() + select_google_location()
    // ═════════════════════════════════════════════════════════════

    // GET /api/social/google-locations/{token}
    public function googleLocations(string $token): JsonResponse
    {
        $data = Cache::get("pending_google_locs:{$token}");

        if (!$data) {
            return response()->json(['detail' => 'Token non valido o scaduto'], 404);
        }

        return response()->json(['locations' => $data['locations']]);
    }

    // POST /api/social/google-locations/{token}/select
    public function selectGoogleLocation(string $token, Request $request): JsonResponse
    {
        $locationId = $request->input('location_id') ?? $request->query('location_id');

        $data = Cache::pull("pending_google_locs:{$token}");
        if (!$data) {
            return response()->json(['detail' => 'Token non valido o scaduto'], 404);
        }

        // Trova la location selezionata (come Python: loop)
        $selectedLocation = null;
        foreach ($data['locations'] as $loc) {
            if ($loc['id'] === $locationId) {
                $selectedLocation = $loc;
                break;
            }
        }

        if (!$selectedLocation) {
            return response()->json(['detail' => 'Location non trovata'], 400);
        }

        // Salva connessione (come Python)
        SocialConnection::where('brand_id', $data['brand_id'])
            ->where('platform', 'google_business')
            ->delete();

        SocialConnection::create([
            'brand_id'              => $data['brand_id'],
            'platform'              => 'google_business',
            'access_token'          => $data['access_token'],
            'refresh_token'         => $data['refresh_token'],
            'token_expires_at'      => now()->addSeconds($data['expires_in']),
            'external_account_id'   => $selectedLocation['id'],
            'external_account_name' => $selectedLocation['name'],
            'account_type'          => 'location',
            'connected_by_user_id'  => $data['user_id'],
            'is_active'             => true,
        ]);

        return response()->json(['success' => true, 'location_name' => $selectedLocation['name']]);
    }
}
