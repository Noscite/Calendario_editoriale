<?php

declare(strict_types=1);

namespace App\Domain\Territorial\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TerritorialEvent extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'categories'    => 'array',
        'raw_payload'   => 'array',
        'start_at'      => 'datetime',
        'end_at'        => 'datetime',
        'first_seen_at' => 'datetime',
        'last_seen_at'  => 'datetime',
        'lat'           => 'float',
        'lng'           => 'float',
    ];

    public function eventPosts(): HasMany
    {
        return $this->hasMany(TerritorialEventPost::class);
    }
}
