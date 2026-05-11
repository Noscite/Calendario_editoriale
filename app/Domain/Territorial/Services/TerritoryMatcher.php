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
            // Overlap window: l'evento è "attivo nel periodo" se inizia entro la fine
            // del range AND finisce dopo l'inizio del range. Recupera anche rassegne
            // multi-mese iniziate prima del range che sono ancora in corso.
            ->where('start_at', '<=', $end)
            ->where(function (Builder $q) use ($start) {
                $q->where('end_at', '>=', $start)
                  ->orWhereNull('end_at'); // eventi puntuali senza end_at: match permissivo
            });

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
     * o nome esteso 'Milano') con tolleranza per:
     * - Case variabile (MI / Mi / mi / Milano / MILANO)
     * - Province non-standard ("Monza Brianza", "Monza e della Brianza")
     * - Dati sporchi (province="Italia"): fallback su match per city con
     *   qualunque comune della regione.
     */
    private function scopeByRegion(Builder $query, string $regionName): void
    {
        // Sigle province della regione (sempre MAIUSCOLE in DB municipalities)
        $sigle = Municipality::where('regione', $regionName)
            ->select('provincia')
            ->distinct()
            ->pluck('provincia')
            ->filter()
            ->values()
            ->toArray();

        if (empty($sigle)) {
            $query->whereRaw('1 = 0');
            return;
        }

        // Nomi capoluoghi (es. "milano", "bergamo") per match nome esteso provincia
        $provinceNames = Municipality::whereIn('provincia', $sigle)
            ->select('nome')
            ->distinct()
            ->pluck('nome')
            ->map(fn (string $n) => mb_strtolower($n, 'UTF-8'))
            ->values()
            ->toArray();

        // Tutti i comuni della regione (case-insensitive) per fallback city
        $cityNames = Municipality::where('regione', $regionName)
            ->pluck('nome')
            ->map(fn (string $n) => mb_strtolower($n, 'UTF-8'))
            ->unique()
            ->values()
            ->toArray();

        // Alias provincia non-standard noti del feed E015.
        // Estendibile in futuro: chiave = stringa UPPER come arriva nel feed,
        // valore = sigla provincia ufficiale.
        $provinceAliases = [
            'MONZA BRIANZA'              => 'MB',
            'MONZA E DELLA BRIANZA'      => 'MB',
            'MONZA E BRIANZA'            => 'MB',
            'PROVINCIA DI MONZA'         => 'MB',
            'PROVINCIA DI MONZA BRIANZA' => 'MB',
        ];
        // Tieni solo gli alias le cui sigle sono effettivamente nella regione
        $applicableAliases = array_keys(array_filter(
            $provinceAliases,
            fn (string $sigla) => in_array($sigla, $sigle, true)
        ));

        $query->where(function (Builder $q) use ($sigle, $provinceNames, $cityNames, $applicableAliases) {
            // 1) Match sigla provincia case-insensitive
            $q->whereIn(\DB::raw('UPPER(province)'), $sigle);

            // 2) OR match nome esteso provincia case-insensitive (es. "Milano", "MILANO", "milano")
            if (! empty($provinceNames)) {
                $q->orWhereIn(\DB::raw('LOWER(province)'), $provinceNames);
            }

            // 3) OR match alias provincia non-standard ("Monza Brianza" -> MB)
            if (! empty($applicableAliases)) {
                $q->orWhereIn(\DB::raw('UPPER(province)'), $applicableAliases);
            }

            // 4) OR fallback: city corrisponde a un comune della regione
            //    (recupera eventi con province sporca tipo "Italia" o NULL)
            if (! empty($cityNames)) {
                $q->orWhereIn(\DB::raw('LOWER(city)'), $cityNames);
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
