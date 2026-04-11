<?php

declare(strict_types=1);

namespace App\Domain\Brand\Services;

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
            'AI (opzionali — usa le chiavi di sistema se vuoti)' => [
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
     * Restituisce la chiave del brand; se assente, fallback al config di sistema.
     */
    public function getOrFallback(Brand $brand, string $keyName, string $configFallback): string
    {
        return $this->get($brand, $keyName) ?? config($configFallback);
    }

    public function set(Brand $brand, string $keyName, string $value): void
    {
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
