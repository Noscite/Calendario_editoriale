<?php

declare(strict_types=1);

namespace App\Domain\Territorial\Services;

use App\Domain\Brand\Models\Brand;
use App\Domain\Territorial\Models\Municipality;
use App\Domain\Territorial\Models\TerritorialEvent;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TerritoryMatcher
{
    /**
     * Ritorna gli eventi territoriali eleggibili per il brand nella finestra temporale.
     *
     * - vertical=null o non riconosciuto → ritorna collection vuota (no pipeline territoriale)
     * - vertical=unpli_regional + territory_meta.region → eventi di tutta la regione
     * - vertical=pro_loco + territory_meta.municipality_istat → eventi del singolo comune
     */
    public function eligibleEvents(Brand $brand, Carbon $start, Carbon $end): Collection
    {
        $vertical = $brand->vertical ?? null;
        $meta     = $brand->territory_meta ?? [];

        if (! in_array($vertical, ['unpli_regional', 'pro_loco'], true)) {
            return collect();
        }

        $query = TerritorialEvent::query()
            ->where('status', 'active')
            ->whereBetween('start_at', [$start, $end]);

        if ($vertical === 'unpli_regional') {
            $region = $meta['region'] ?? null;
            if (! $region) {
                return collect();
            }
            $this->scopeByRegion($query, $region);
        } elseif ($vertical === 'pro_loco') {
            $istat = $meta['municipality_istat'] ?? null;
            if (! $istat) {
                return collect();
            }
            $this->scopeByMunicipality($query, $istat);
        }

        return $query->orderBy('start_at')->get();
    }

    /**
     * Scope per regione: matcha eventi via province della regione (sigla 'MI'
     * o nome esteso 'Milano' — il feed E015 alterna i due formati).
     */
    private function scopeByRegion(Builder $query, string $regionName): void
    {
        $municipalities = Municipality::where('regione', $regionName)
            ->select('provincia')
            ->distinct()
            ->pluck('provincia')
            ->filter()
            ->values()
            ->toArray();

        if (empty($municipalities)) {
            $query->whereRaw('1 = 0'); // nessun match
            return;
        }

        // Per il match "nome esteso provincia" (es. 'Milano') prendiamo dalla
        // tabella province di Italia derivata. Per semplicità e dato che il
        // feed E015 usa il nome del capoluogo come "province" esteso, mappiamo
        // sigla -> nome capoluogo via Municipality stesso (cerchiamo i comuni
        // capoluogo: in pratica accettiamo qualunque comune con quella sigla
        // come potenziale "province name").
        $provinceNames = Municipality::whereIn('provincia', $municipalities)
            ->select('nome')
            ->distinct()
            ->pluck('nome')
            ->map(fn (string $n) => mb_strtolower($n, 'UTF-8'))
            ->toArray();

        $query->where(function (Builder $q) use ($municipalities, $provinceNames) {
            $q->whereIn('province', $municipalities); // sigla 'MI', 'BG', ...

            // OR fallback: feed E015 a volte scrive il nome esteso "Milano" / "Bergamo"
            if (! empty($provinceNames)) {
                $q->orWhereIn(\DB::raw('LOWER(province)'), $provinceNames);
            }
        });
    }

    /**
     * Scope per singolo comune: match case-insensitive su `city`, con varianti
     * apostrofo e accenti per resistere alla normalizzazione approssimativa
     * dei feed esterni.
     */
    private function scopeByMunicipality(Builder $query, string $istatCode): void
    {
        $municipality = Municipality::find($istatCode);
        if (! $municipality) {
            $query->whereRaw('1 = 0');
            return;
        }

        $original = mb_strtolower($municipality->nome, 'UTF-8');
        $normalized = Municipality::normalize($municipality->nome);

        $query->where(function (Builder $q) use ($original, $normalized) {
            // Match diretto case-insensitive sulla forma "ufficiale"
            $q->whereRaw('LOWER(city) = ?', [$original])
              // Match sulla forma normalizzata (no accenti, apostrofi straight)
              ->orWhereRaw('LOWER(city) = ?', [$normalized])
              // Match con apostrofo curly normalizzato a straight
              ->orWhereRaw("LOWER(REPLACE(city, '\u{2019}', '''')) = ?", [$original]);
        });
    }
}
