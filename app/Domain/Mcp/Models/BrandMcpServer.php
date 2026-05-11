<?php

declare(strict_types=1);

namespace App\Domain\Mcp\Models;

use App\Domain\Brand\Models\Brand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * MCP server configurato a livello brand.
 *
 * Default ereditato da TUTTE le campagne del brand (a meno che la campagna
 * abbia override_brand_mcp=true sul suo CampaignMcpServer).
 *
 * api_key è cifrata at-rest via Laravel Crypt (APP_KEY).
 */
final class BrandMcpServer extends Model
{
    protected $fillable = [
        'brand_id',
        'name',
        'url',
        'encrypted_api_key',
        'scopes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'scopes'    => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function setApiKey(?string $rawKey): void
    {
        $this->encrypted_api_key = $rawKey ? Crypt::encryptString($rawKey) : null;
    }

    public function getApiKey(): ?string
    {
        if (! $this->encrypted_api_key) {
            return null;
        }
        try {
            return Crypt::decryptString($this->encrypted_api_key);
        } catch (\Throwable) {
            return null;
        }
    }
}
