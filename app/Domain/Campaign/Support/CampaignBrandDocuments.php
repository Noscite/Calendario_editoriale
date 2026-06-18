<?php

declare(strict_types=1);

namespace App\Domain\Campaign\Support;

use App\Domain\Campaign\Models\Campaign;
use Illuminate\Support\Facades\DB;

/**
 * Logica condivisa per la selezione di documenti KB del brand sulla campagna
 * (pivot campaign_brand_document). Centralizzata perché usata da più punti
 * (CampaignController, ProjectCampaignController, CampaignGenerationService) e
 * include un controllo di sicurezza (anti-IDOR) che non deve divergere.
 */
final class CampaignBrandDocuments
{
    /**
     * Normalizza il payload brand_documents: accetta array nativo (JSON body)
     * o stringa JSON (multipart FormData). Restituisce null se assente, così il
     * chiamante può distinguere "campo non inviato" (=> non toccare la selezione)
     * da "[]" (=> svuota).
     *
     * @return array<int, array{id:int, inject_mode?:string}>|null
     */
    public static function parse(mixed $raw): ?array
    {
        if ($raw === null) {
            return null;
        }
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * Sincronizza la pivot campaign_brand_document.
     * - $docs === null  => campo assente: NON tocca la selezione esistente.
     * - $docs === []    => selezione esplicitamente svuotata.
     * - in_array guard  => inject_mode non valido da payload manipolato => 'summary'.
     * - filtro org      => Anti-IDOR: tiene solo i documenti il cui brand
     *   appartiene all'organization. brand_documents non ha org-scope globale,
     *   quindi senza questo filtro un payload manipolato potrebbe agganciare
     *   (e far iniettare nella generazione) documenti di un altro tenant.
     *
     * @param  array<int, array{id:int, inject_mode?:string}>|null  $docs
     */
    public static function sync(Campaign $campaign, ?array $docs, int $orgId): void
    {
        if ($docs === null) {
            return;
        }

        $allowedIds = DB::table('brand_documents')
            ->join('brands', 'brands.id', '=', 'brand_documents.brand_id')
            ->whereIn('brand_documents.id', collect($docs)->pluck('id')->all())
            ->where('brands.organization_id', $orgId)
            ->pluck('brand_documents.id')
            ->all();

        $campaign->brandDocuments()->sync(
            collect($docs)
                ->filter(fn ($d) => in_array($d['id'] ?? null, $allowedIds, true))
                ->mapWithKeys(fn ($d) => [
                    $d['id'] => ['inject_mode' => in_array($d['inject_mode'] ?? 'summary', ['summary', 'full_text'], true) ? $d['inject_mode'] : 'summary'],
                ])
        );
    }
}
