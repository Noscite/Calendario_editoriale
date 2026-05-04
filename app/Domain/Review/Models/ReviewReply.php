<?php

declare(strict_types=1);

namespace App\Domain\Review\Models;

use App\Domain\Review\Enums\ReplyStatus;
use App\Domain\Review\Enums\ReplyTone;
use App\Domain\Shared\Traits\BelongsToOrganization;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewReply extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'review_id',
        'status',
        'body',
        'original_body',
        'tone_used',
        'marketing_strategy',
        'kb_chunks_used',
        'generated_by_model',
        'input_tokens',
        'output_tokens',
        'generated_at',
        'approved_by_user_id',
        'approved_at',
        'was_edited',
        'sent_at',
        'external_reply_id',
        'error_message',
        'retry_count',
    ];

    protected function casts(): array
    {
        return [
            'status'          => ReplyStatus::class,
            'tone_used'       => ReplyTone::class,
            'kb_chunks_used'  => 'array',
            'generated_at'    => 'datetime',
            'approved_at'     => 'datetime',
            'sent_at'         => 'datetime',
            'was_edited'      => 'boolean',
            'input_tokens'    => 'integer',
            'output_tokens'   => 'integer',
            'retry_count'     => 'integer',
        ];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
