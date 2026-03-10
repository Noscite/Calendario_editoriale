<?php

namespace App\Domain\Document\Models;

use App\Domain\Brand\Models\Brand;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentChunk extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'document_id',
        'brand_id',
        // Contenuto
        'chunk_index',
        'content',
        'token_count',
        // Embedding
        'embedding',
        // Metadata
        'chunk_type',
        'page_number',
    ];

    protected function casts(): array
    {
        return [
            'chunk_index' => 'integer',
            'token_count' => 'integer',
            'page_number' => 'integer',
        ];
    }

    // ── Relations ──────────────────────────────────────────────

    public function document(): BelongsTo
    {
        return $this->belongsTo(BrandDocument::class, 'document_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
