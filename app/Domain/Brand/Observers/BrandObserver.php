<?php

declare(strict_types=1);

namespace App\Domain\Brand\Observers;

use App\Domain\Brand\Models\Brand;
use App\Domain\Review\Jobs\BootstrapBrandOntologyJob;

/**
 * Auto-bootstrap dell'ontologia review quando il brand ha materiale sufficiente.
 *
 * Trigger:
 *   - created: se sector non vuoto E description >= 50 char E ontologia null/empty
 *   - updated: stesso check, MA se sector cambia rispetto all'originale,
 *     l'ontologia (anche se esistente) viene azzerata e ricostruita —
 *     l'identità del brand è cambiata, l'ontologia vecchia non è più valida.
 *     Se solo description cambia, NON re-trigger.
 */
final class BrandObserver
{
    private const MIN_DESCRIPTION_LENGTH = 50;

    public function created(Brand $brand): void
    {
        $this->maybeDispatchOntologyBootstrap($brand);
    }

    public function updated(Brand $brand): void
    {
        $hadOntology   = ! empty($brand->getOriginal('review_ontology'));
        $sectorChanged = $brand->wasChanged('sector');

        if ($sectorChanged) {
            if ($hadOntology) {
                // Update senza ri-fire dell'observer (evita ricorsione su `updated`).
                Brand::withoutGlobalScope('organization')
                    ->where('id', $brand->id)
                    ->update(['review_ontology' => null]);
                $brand->review_ontology = null;
                $brand->syncOriginalAttribute('review_ontology');
            }
            $this->maybeDispatchOntologyBootstrap($brand);
            return;
        }

        if (! $hadOntology) {
            $this->maybeDispatchOntologyBootstrap($brand);
        }
    }

    private function maybeDispatchOntologyBootstrap(Brand $brand): void
    {
        if (! $this->meetsBootstrapConditions($brand)) {
            return;
        }
        if (! empty($brand->review_ontology)) {
            return;
        }
        BootstrapBrandOntologyJob::dispatch($brand->id);
    }

    private function meetsBootstrapConditions(Brand $brand): bool
    {
        return trim((string) $brand->sector) !== ''
            && mb_strlen(trim((string) $brand->description)) >= self::MIN_DESCRIPTION_LENGTH;
    }
}
