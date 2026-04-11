<?php

declare(strict_types=1);

namespace App\Domain\Brand\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandApiKey extends Model
{
    protected $fillable = [
        'brand_id',
        'key_name',
        'encrypted_value',
    ];

    protected $casts = [
        'encrypted_value' => 'encrypted',
    ];

    protected $hidden = ['encrypted_value'];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
