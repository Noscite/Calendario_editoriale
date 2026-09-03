<?php

declare(strict_types=1);

namespace App\Domain\Generation\Models;

use App\Domain\Brand\Models\Brand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Override dei parametri di generazione AI (modello, temperature, max_tokens,
 * top_p/top_k, prompt caching) per singolo step della pipeline.
 * brand_id NULL = default globale; valorizzato = override per quel brand.
 * Ogni campo NULL nella riga significa "eredita dal livello superiore"
 * (brand → globale → costante hardcoded), risolto da AiGenerationSettingsService.
 */
class AiGenerationSetting extends Model
{
    protected $fillable = [
        'brand_id',
        'step',
        'model',
        'temperature',
        'max_tokens',
        'top_p',
        'top_k',
        'prompt_caching_enabled',
    ];

    protected $casts = [
        'temperature'             => 'float',
        'max_tokens'              => 'integer',
        'top_p'                   => 'float',
        'top_k'                   => 'integer',
        'prompt_caching_enabled'  => 'boolean',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
