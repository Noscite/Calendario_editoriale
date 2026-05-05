<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Brand\Models\Brand;
use App\Domain\Review\Contracts\OntologyBootstrapServiceInterface;
use Illuminate\Console\Command;

class BootstrapReviewOntologyCommand extends Command
{
    protected $signature = 'reviews:bootstrap-ontology
        {brand_id : ID del brand per cui generare l\'ontologia}
        {--force : Sovrascrive l\'ontologia esistente senza chiedere conferma}';

    protected $description = 'Genera l\'ontologia dei topic per le review di un brand via Claude (analisi settore + KB + review esistenti).';

    public function handle(OntologyBootstrapServiceInterface $service): int
    {
        $brandId = (int) $this->argument('brand_id');
        $brand   = Brand::withoutGlobalScope('organization')->find($brandId);

        if (! $brand) {
            $this->error("Brand #{$brandId} non trovato.");
            return self::FAILURE;
        }

        $existing = $brand->review_ontology;
        if (is_array($existing) && $existing !== [] && ! $this->option('force')) {
            $this->warn("Brand '{$brand->name}' ha già un'ontologia con " . count($existing) . ' topic.');
            if (! $this->confirm('Sovrascrivere?', false)) {
                $this->info('Annullato.');
                return self::SUCCESS;
            }
        }

        $this->info("Generazione ontologia per brand '{$brand->name}' (settore: " . ($brand->sector ?? 'n/d') . ')...');

        try {
            $ontology = $service->bootstrapForBrand($brand);
        } catch (\Throwable $e) {
            $this->error('Errore: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info('Ontologia generata: ' . count($ontology) . ' topic.');
        $this->table(
            ['ID', 'Label', 'Descrizione'],
            array_map(
                fn (array $t): array => [$t['id'], $t['label'], $t['description']],
                $ontology,
            ),
        );

        return self::SUCCESS;
    }
}
