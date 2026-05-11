<?php

declare(strict_types=1);

namespace App\Domain\Campaign\Models;

use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CampaignAttachment extends Model
{
    public const STATUS_PENDING     = 'pending';
    public const STATUS_PROCESSING  = 'processing';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_FAILED      = 'failed';
    public const STATUS_UNSUPPORTED = 'unsupported';

    protected $fillable = [
        'campaign_id',
        'uploaded_by_user_id',
        'original_filename',
        'stored_filename',
        'mime_type',
        'size_bytes',
        'extracted_text',
        'extraction_status',
        'extraction_error',
        'extracted_at',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes'   => 'integer',
            'extracted_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function getStoragePath(): string
    {
        return storage_path("app/campaign-attachments/{$this->campaign_id}/{$this->stored_filename}");
    }

    public function getDownloadUrl(): string
    {
        return route('campaign-attachments.download', ['attachment' => $this->id]);
    }
}
