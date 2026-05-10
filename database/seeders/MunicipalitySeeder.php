<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Territorial\Models\Municipality;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Importa l'elenco completo dei comuni italiani (~7900) da una sorgente JSON
 * pubblica (matteocontrini/comuni-json, mirror del dato ISTAT).
 *
 * Il task originale referenziava api.daticomuni.it, che attualmente ha un
 * SSL hostname mismatch e non risponde — sostituito con questo mirror GitHub
 * raw che è massimamente affidabile e ha shape JSON simile.
 *
 * One-shot: dopo l'esecuzione, l'autocomplete del BrandResource Filament
 * usa solo il DB locale, zero dipendenza runtime dall'API esterna.
 */
class MunicipalitySeeder extends Seeder
{
    private const SOURCE_URL = 'https://raw.githubusercontent.com/matteocontrini/comuni-json/master/comuni.json';

    public function run(): void
    {
        $this->command?->info("Fetching municipalities JSON from " . self::SOURCE_URL);

        try {
            $response = Http::timeout(60)
                ->retry(3, 2000)
                ->get(self::SOURCE_URL);

            if (! $response->successful()) {
                $this->command?->error("HTTP {$response->status()}, abort.");
                return;
            }

            $items = $response->json();
            if (! is_array($items)) {
                $this->command?->error("Response is not a JSON array.");
                return;
            }

            $this->command?->info("Importing " . count($items) . " municipalities...");

            $totalImported = 0;
            $totalErrors = 0;

            foreach ($items as $item) {
                $codice = $item['codice'] ?? null;
                $nome = $item['nome'] ?? null;
                $sigla = $item['sigla'] ?? '';
                $regione = $item['regione']['nome'] ?? '';

                if (! $codice || ! $nome) {
                    $totalErrors++;
                    continue;
                }

                Municipality::updateOrCreate(
                    ['codice_istat' => $codice],
                    [
                        'nome'            => $nome,
                        'nome_normalized' => Municipality::normalize($nome),
                        'provincia'       => $sigla,
                        'regione'         => $regione,
                        'lat'             => null, // non presente in questo dataset
                        'lng'             => null,
                    ]
                );
                $totalImported++;
            }

            $this->command?->info("✓ Total imported: {$totalImported} (skipped malformed: {$totalErrors})");

        } catch (\Throwable $e) {
            Log::error("MunicipalitySeeder error: {$e->getMessage()}");
            $this->command?->error("Exception: {$e->getMessage()}");
        }
    }
}
