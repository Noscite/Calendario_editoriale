<?php

namespace App\Domain\Document\Models;

use App\Domain\Brand\Models\Brand;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BrandDocument extends Model
{
    use HasFactory;

    protected $table = 'brand_documents';

    const UPDATED_AT = null;
    const CREATED_AT = 'uploaded_at';

    protected $fillable = [
        'brand_id',
        // File info
        'filename',
        'original_filename',
        'file_type',
        'file_size',
        'file_path',
        // Stato elaborazione
        'extraction_status',
        'analysis_status',
        // Metadata
        'uploaded_by_user_id',
        'analyzed_at',
        'description',
        // AI generated
        'summary',
        'key_topics',
    ];

    protected $attributes = [
        'extraction_status' => 'pending',
        'analysis_status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'uploaded_at' => 'datetime',
            'analyzed_at' => 'datetime',
            'key_topics' => 'json',
        ];
    }

    // ── Relations ──────────────────────────────────────────────

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class, 'document_id');
    }
}
