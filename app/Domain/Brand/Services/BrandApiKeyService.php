<?php

declare(strict_types=1);

namespace App\Domain\Brand\Services;

use App\Domain\Brand\Exceptions\MissingBrandApiKeyException;
use App\Domain\Brand\Models\Brand;
use App\Domain\Brand\Models\BrandApiKey;
use App\Domain\Organization\Models\Organization;
use App\Domain\Subscription\Models\Plan;
use Illuminate\Support\Facades\Auth;

class BrandApiKeyService
{
    // ── Meta (Facebook / Instagram) ───────────────────────────────────────────
    public const META_APP_ID        = 'meta_app_id';
    public const META_APP_SECRET    = 'meta_app_secret';
    public const META_ACCESS_TOKEN  = 'meta_access_token';
    public const META_PAGE_ID       = 'meta_page_id';
    public const META_IG_ACCOUNT_ID = 'meta_ig_account_id';

    // ── LinkedIn ──────────────────────────────────────────────────────────────
    public const LINKEDIN_CLIENT_ID       = 'linkedin_client_id';
    public const LINKEDIN_CLIENT_SECRET   = 'linkedin_client_secret';
    public const LINKEDIN_ACCESS_TOKEN    = 'linkedin_access_token';
    public const LINKEDIN_ORGANIZATION_ID = 'linkedin_organization_id';

    // ── Google Business Profile ───────────────────────────────────────────────
    public const GOOGLE_CLIENT_ID     = 'google_client_id';
    public const GOOGLE_CLIENT_SECRET = 'google_client_secret';
    public const GOOGLE_ACCESS_TOKEN  = 'google_access_token';
    public const GOOGLE_REFRESH_TOKEN = 'google_refresh_token';
    public const GOOGLE_LOCATION_ID   = 'google_location_id';

    // ── AI ────────────────────────────────────────────────────────────────────
    public const ANTHROPIC_API_KEY  = 'anthropic_api_key';
    public const OPENAI_API_KEY     = 'openai_api_key';
    public const PERPLEXITY_API_KEY = 'perplexity_api_key';

    // ── Gruppi per la UI ──────────────────────────────────────────────────────

    public static function groups(): array
    {
        return [
            'Meta (Facebook / Instagram)' => [
                self::META_APP_ID        => 'App ID',
                self::META_APP_SECRET    => 'App Secret',
                self::META_ACCESS_TOKEN  => 'Access Token (Page)',
                self::META_PAGE_ID       => 'Page ID',
                self::META_IG_ACCOUNT_ID => 'Instagram Account ID',
            ],
            'LinkedIn' => [
                self::LINKEDIN_CLIENT_ID       => 'Client ID',
                self::LINKEDIN_CLIENT_SECRET   => 'Client Secret',
                self::LINKEDIN_ACCESS_TOKEN    => 'Access Token',
                self::LINKEDIN_ORGANIZATION_ID => 'Organization ID',
            ],
            'Google Business Profile' => [
                self::GOOGLE_CLIENT_ID     => 'Client ID',
                self::GOOGLE_CLIENT_SECRET => 'Client Secret',
                self::GOOGLE_ACCESS_TOKEN  => 'Access Token',
                self::GOOGLE_REFRESH_TOKEN => 'Refresh Token',
                self::GOOGLE_LOCATION_ID   => 'Location ID',
            ],
            'AI' => [
                self::ANTHROPIC_API_KEY  => 'Anthropic API Key',
                self::OPENAI_API_KEY     => 'OpenAI API Key',
                self::PERPLEXITY_API_KEY => 'Perplexity API Key',
            ],
        ];
    }

    // ── CRUD ──────────────────────────────────────────────────────────────────

    public function get(Brand $brand, string $keyName): ?string
    {
        $record = BrandApiKey::where('brand_id', $brand->id)
            ->where('key_name', $keyName)
            ->first();

        return $record?->encrypted_value;
    }

    /**
     * Restituisce la chiave del brand.
     * Lancia MissingBrandApiKeyException se non configurata.
     * NON fa mai fallback alle chiavi di sistema — quelle appartengono a Noscite.
     */
    public function getRequired(Brand $brand, string $keyName): string
    {
        $value = $this->get($brand, $keyName);

        if ($value === null || $value === '') {
            throw new MissingBrandApiKeyException($keyName, $brand->name);
        }

        return $value;
    }

    /**
     * Fallback alle chiavi di sistema consentito quando:
     *   1. L'utente loggato è superuser (UI/web context), oppure
     *   2. Il brand appartiene a un'organization marcata is_system_tenant, oppure
     *   3. Siamo in queue/CLI context (no Auth user + running in console):
     *      job di sistema legittimi che non hanno chiave brand-level
     *      ricadono sulla chiave di config. Il dispatch del job era già
     *      stato autorizzato a monte da un'azione utente.
     * Negli altri casi lancia MissingBrandApiKeyException.
     */
    public function getWithSuperAdminFallback(
        Brand $brand,
        string $keyName,
        string $configFallback
    ): string {
        $value = $this->get($brand, $keyName);

        if ($value !== null && $value !== '') {
            return $value;
        }

        if (
            $this->currentUserIsSuperAdmin()
            || $this->brandBelongsToSystemTenant($brand)
            || $this->isQueueContext()
        ) {
            return config($configFallback)
                ?? throw new \RuntimeException("Chiave di sistema non configurata: {$configFallback}");
        }

        throw new MissingBrandApiKeyException($keyName, $brand->name);
    }

    private function currentUserIsSuperAdmin(): bool
    {
        return Auth::user()?->role === 'superuser';
    }

    private function brandBelongsToSystemTenant(Brand $brand): bool
    {
        return Organization::query()
            ->whereKey($brand->organization_id)
            ->where('is_system_tenant', true)
            ->exists();
    }

    /**
     * Vero solo in CLI/queue context (Horizon worker, artisan command, scheduler).
     * In HTTP request normale `runningInConsole()` è false anche senza Auth user.
     * Doppio predicato (no Auth + console) per evitare bypass su endpoint HTTP
     * non autenticati edge-case.
     */
    private function isQueueContext(): bool
    {
        return Auth::user() === null && app()->runningInConsole();
    }

    /**
     * Ritorna le chiavi obbligatorie non configurate per un brand.
     * Usato per validazione pre-generazione nell'UI.
     */
    public function getMissingRequiredKeys(Brand $brand): array
    {
        $required = [
            self::ANTHROPIC_API_KEY  => 'Anthropic (generazione testi)',
            self::PERPLEXITY_API_KEY => 'Perplexity (ricerca trend)',
        ];

        $existing = $this->getAll($brand);
        $missing  = [];

        foreach ($required as $keyName => $label) {
            if (empty($existing[$keyName])) {
                $missing[$keyName] = $label;
            }
        }

        return $missing;
    }

    public function set(Brand $brand, string $keyName, string $value): void
    {
        $org  = Organization::find($brand->organization_id);
        $plan = $org?->plan_id ? Plan::find($org->plan_id) : null;

        if (! $plan?->has_own_api_keys && ! $this->currentUserIsSuperAdmin()) {
            throw new \DomainException(
                'Il piano attuale non include la gestione di chiavi API proprie. ' .
                'Passa al piano Unlimited per abilitare questa funzionalità.'
            );
        }

        BrandApiKey::updateOrCreate(
            ['brand_id' => $brand->id, 'key_name' => $keyName],
            ['encrypted_value' => $value]
        );
    }

    public function delete(Brand $brand, string $keyName): void
    {
        BrandApiKey::where('brand_id', $brand->id)
            ->where('key_name', $keyName)
            ->delete();
    }

    public function getAll(Brand $brand): array
    {
        return BrandApiKey::where('brand_id', $brand->id)
            ->get()
            ->pluck('encrypted_value', 'key_name')
            ->toArray();
    }

    /**
     * Salva un array [key_name => value]. Valori null o '' eliminano la chiave.
     */
    public function saveMany(Brand $brand, array $data): void
    {
        foreach ($data as $keyName => $value) {
            if ($value === null || $value === '') {
                $this->delete($brand, $keyName);
            } else {
                $this->set($brand, $keyName, (string) $value);
            }
        }
    }
}
