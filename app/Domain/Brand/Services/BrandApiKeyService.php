<?php

declare(strict_types=1);

namespace App\Domain\Brand\Services;

use App\Domain\Brand\Exceptions\MissingBrandApiKeyException;
use App\Domain\Brand\Models\Brand;
use App\Domain\Brand\Models\BrandApiKey;

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

    // ── AI keys @deprecated ───────────────────────────────────────────────────
    // Le chiavi AI sono ora servite SEMPRE da .env (config/services.php).
    // Le costanti restano solo per identificare/pulire eventuali record DB legacy.
    public const ANTHROPIC_API_KEY  = 'anthropic_api_key';
    public const OPENAI_API_KEY     = 'openai_api_key';
    public const PERPLEXITY_API_KEY = 'perplexity_api_key';

    private const AI_KEYS = [
        self::ANTHROPIC_API_KEY,
        self::OPENAI_API_KEY,
        self::PERPLEXITY_API_KEY,
    ];

    // ── Gruppi per la UI ──────────────────────────────────────────────────────
    // Solo chiavi social: identificano l'account del cliente, non possono
    // arrivare da .env Noscite. Le chiavi AI sono fuori da questo elenco.
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
     * Restituisce la chiave del brand. Usata solo per chiavi social
     * (Meta/LinkedIn/Google). Lancia MissingBrandApiKeyException se non
     * configurata.
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
     * @deprecated Le chiavi AI vengono sempre da .env. Ritorna sempre [].
     *             Mantenuto per retrocompatibilità con i call site esistenti
     *             (GenerationController preflight). Eliminabile in futuro
     *             insieme ai call site.
     */
    public function getMissingRequiredKeys(Brand $brand): array
    {
        return [];
    }

    public function set(Brand $brand, string $keyName, string $value): void
    {
        // Le chiavi AI provengono da .env: ignora silenziosamente eventuali
        // tentativi di salvarle (legacy UI, API client esterni, etc).
        if (in_array($keyName, self::AI_KEYS, true)) {
            return;
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
     * Le chiavi AI vengono filtrate da set() (gestite via .env).
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
