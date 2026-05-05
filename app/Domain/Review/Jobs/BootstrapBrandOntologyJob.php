<?php

declare(strict_types=1);

namespace App\Domain\Review\Jobs;

use App\Domain\Brand\Models\Brand;
use App\Domain\Review\Contracts\OntologyBootstrapServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Genera l'ontologia di review per un brand in modo asincrono.
 *
 * Trigger: BrandObserver (created/updated con sector + description sufficienti).
 *
 * Idempotente: salta se l'ontologia è già popolata (es. doppio dispatch
 * o utente che la edita manualmente fra il dispatch e l'esecuzione).
 */
class BootstrapBrandOntologyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries     = 2;
    public int $timeout   = 60;
    /** @var array<int,int> */
    public array $backoff = [60, 300];

    private const MIN_DESCRIPTION_LENGTH = 50;

    public function __construct(
        private readonly int $brandId,
    ) {
        $this->onQueue('default');
    }

    public function handle(OntologyBootstrapServiceInterface $bootstrap): void
    {
        $brand = Brand::withoutGlobalScope('organization')->find($this->brandId);
        if (! $brand) {
            Log::info('[ONTOLOGY_BOOTSTRAP] Brand non trovato', ['brand_id' => $this->brandId]);
            return;
        }

        // Idempotenza: ontologia già popolata
        if (is_array($brand->review_ontology) && $brand->review_ontology !== []) {
            Log::info('[ONTOLOGY_BOOTSTRAP] Ontologia già presente, skip', [
                'brand_id' => $brand->id,
                'count'    => count($brand->review_ontology),
            ]);
            return;
        }

        // Verifica condizioni minime (potrebbero essere cambiate fra dispatch ed esecuzione)
        $sector      = trim((string) $brand->sector);
        $description = trim((string) $brand->description);
        if ($sector === '' || mb_strlen($description) < self::MIN_DESCRIPTION_LENGTH) {
            Log::info('[ONTOLOGY_BOOTSTRAP] Skipped: insufficient_data', [
                'brand_id'           => $brand->id,
                'sector_present'     => $sector !== '',
                'description_length' => mb_strlen($description),
            ]);
            return;
        }

        try {
            $ontology = $bootstrap->bootstrapForBrand($brand);
            Log::info('[ONTOLOGY_BOOTSTRAP] Ontologia generata', [
                'brand_id' => $brand->id,
                'count'    => count($ontology),
            ]);
        } catch (\Throwable $e) {
            Log::error('[ONTOLOGY_BOOTSTRAP] Generazione fallita', [
                'brand_id' => $brand->id,
                'error'    => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[ONTOLOGY_BOOTSTRAP] Job failed permanently', [
            'brand_id' => $this->brandId,
            'error'    => $exception->getMessage(),
        ]);
    }
}
