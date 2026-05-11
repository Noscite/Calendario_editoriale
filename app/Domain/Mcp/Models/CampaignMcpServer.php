<?php

declare(strict_types=1);

namespace App\Domain\Mcp\Models;

use App\Domain\Campaign\Models\Campaign;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * MCP server configurato a livello campagna.
 *
 * Override / addizione rispetto agli MCP del brand:
 * - Se override_brand_mcp=false (default): i campaign MCP si AGGIUNGONO ai
 *   brand MCP (union).
 * - Se override_brand_mcp=true: gli MCP del brand sono ESCLUSI, vengono usati
 *   solo i campaign MCP.
 *
 * api_key è cifrata at-rest via Laravel Crypt (APP_KEY).
 */
final class CampaignMcpServer extends Model
{
    protected $fillable = [
        'campaign_id',
        'name',
        'url',
        'encrypted_api_key',
        'scopes',
        'is_active',
        'override_brand_mcp',
    ];

    protected function casts(): array
    {
        return [
            'scopes'             => 'array',
            'is_active'          => 'boolean',
            'override_brand_mcp' => 'boolean',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
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
