<?php

declare(strict_types=1);

namespace App\Domain\Territorial\Models;

use App\Domain\Post\Models\Post;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerritorialEventPost extends Model
{
    protected $guarded = [];

    protected $casts = [
        'generated_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(TerritorialEvent::class, 'territorial_event_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
