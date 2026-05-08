<?php

declare(strict_types=1);

namespace App\Domain\Post\Models;

use App\Domain\Organization\Models\Organization;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log delle modifiche manuali fatte dagli utenti su post AI-generated.
 * Costruisce il dataset di debugging: cosa l'AI ha sbagliato, come l'umano
 * ha corretto.
 */
final class PostEditLog extends Model
{
    protected $fillable = [
        'post_id',
        'organization_id',
        'user_id',
        'field_name',
        'original_value',
        'new_value',
        'edit_reason',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
