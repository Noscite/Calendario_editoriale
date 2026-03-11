<?php

declare(strict_types=1);

namespace App\Domain\Help\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpArticle extends Model
{
    protected $fillable = [
        'help_category_id', 'slug', 'title', 'content', 'excerpt',
        'sort_order', 'is_active', 'is_featured', 'views',
    ];

    protected function casts(): array
    {
        return [
            'is_active'   => 'boolean',
            'is_featured' => 'boolean',
            'sort_order'  => 'integer',
            'views'       => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(HelpCategory::class, 'help_category_id');
    }
}
