<?php

declare(strict_types=1);

namespace App\Domain\Territorial\Models;

use Illuminate\Database\Eloquent\Model;

class Municipality extends Model
{
    protected $primaryKey = 'codice_istat';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
    ];

    /**
     * Normalizza una stringa nome comune per match (lowercase, no accenti, apostrofi normalizzati).
     */
    public static function normalize(string $name): string
    {
        $normalized = mb_strtolower(trim($name), 'UTF-8');
        // Normalizza apostrofi (curly → straight)
        $normalized = str_replace(["\u{2019}", "\u{2018}", '`'], "'", $normalized);
        // Rimuovi accenti via iconv
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        if ($ascii !== false) {
            $normalized = $ascii;
        }
        // Rimuovi caratteri non-alfanumerici tranne spazi e apostrofi
        $normalized = preg_replace("/[^a-z0-9 ']/", '', $normalized);
        // Comprimi spazi multipli
        $normalized = preg_replace('/\s+/', ' ', trim($normalized));

        return $normalized;
    }
}
