<?php

declare(strict_types=1);

namespace App\Domain\Brand\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot model per associazione brand ↔ vincolo deontologico.
 *
 * I `constraint_slug` validi sono identificati dall'enum
 * App\Domain\Brand\Enums\Sector (case regolamentati). Validazione
 * referenziale a livello applicativo via FormRequest/Brand::syncDeontologicalConstraints().
 */
final class BrandDeontologicalConstraint extends Model
{
    protected $table = 'brand_deontological_constraints';

    protected $fillable = [
        'brand_id',
        'constraint_slug',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
