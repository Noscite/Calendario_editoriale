<?php

declare(strict_types=1);

use App\Domain\Brand\Models\Brand;
use App\Domain\Brand\Services\BrandCompletenessService;
use App\Domain\Document\Models\BrandDocument;
use Illuminate\Support\Facades\Bus;

/**
 * Pesi del completeness:
 *   identity         15  (name + description ≥80 + sector)
 *   voice            20  (tone_of_voice + voice_examples ≥3 con content ≥20 char)
 *   narrative_assets 25  (≥1 entry valida)
 *   usp_pillars      25  (10 USP+values ≥2 / 15 default_content_pillars 4-6)
 *   kb               15  (≥1 BrandDocument)
 */
describe('BrandCompletenessService', function () {

    beforeEach(function () {
        // Evita il dispatch sync di BootstrapBrandOntologyJob (osservatore Brand)
        // che chiamerebbe Anthropic in test e fallirebbe su MissingBrandApiKey.
        Bus::fake();
        $this->service = app(BrandCompletenessService::class);
    });

    it('brand vuoto produce score 0 e tutte sezioni incomplete', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org, ['name' => 'X', 'sector' => null, 'description' => null, 'tone_of_voice' => null]);

        $result = $this->service->score($brand);

        expect($result['score'])->toBe(0)
            ->and($result['can_generate'])->toBeFalse()
            ->and($result['threshold'])->toBe(70)
            ->and($result['sections']['identity']['complete'])->toBeFalse()
            ->and($result['sections']['voice']['complete'])->toBeFalse()
            ->and($result['sections']['narrative_assets']['complete'])->toBeFalse()
            ->and($result['sections']['usp_pillars']['complete'])->toBeFalse()
            ->and($result['sections']['kb']['complete'])->toBeFalse()
            ->and($result['missing'])->not->toBeEmpty();
    });

    it('brand con identity completa ritorna score 15 e identity.complete = true', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org, [
            'name'        => 'Acme SRL',
            'description' => str_repeat('Descrizione completa del brand per onboarding wizard. ', 3), // ≥80 char
            'sector'      => 'Tech',
            'tone_of_voice' => null,
        ]);

        $result = $this->service->score($brand);

        expect($result['score'])->toBe(15)
            ->and($result['sections']['identity']['complete'])->toBeTrue()
            ->and($result['sections']['identity']['earned'])->toBe(15)
            ->and($result['sections']['voice']['complete'])->toBeFalse();
    });

    it('brand con identity + voice ritorna score 35', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org, [
            'name'           => 'Acme SRL',
            'description'    => str_repeat('Descrizione completa del brand per onboarding wizard. ', 3),
            'sector'         => 'Tech',
            'tone_of_voice'  => 'professionale ma accessibile',
            'voice_examples' => [
                ['platform' => 'linkedin', 'content' => str_repeat('Esempio post linkedin numero uno. ', 3)],
                ['platform' => 'instagram', 'content' => str_repeat('Esempio post instagram numero due. ', 3)],
                ['platform' => 'facebook', 'content' => str_repeat('Esempio post facebook numero tre. ', 3)],
            ],
        ]);

        $result = $this->service->score($brand);

        expect($result['score'])->toBe(35)
            ->and($result['sections']['identity']['complete'])->toBeTrue()
            ->and($result['sections']['voice']['complete'])->toBeTrue()
            ->and($result['sections']['voice']['earned'])->toBe(20);
    });

    it('brand pieno (tutte 5 sezioni) ritorna score 100 e can_generate true', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org, [
            'name'           => 'Acme SRL',
            'description'    => str_repeat('Descrizione completa del brand per onboarding wizard. ', 3),
            'sector'         => 'Tech',
            'tone_of_voice'  => 'professionale ma accessibile',
            'voice_examples' => [
                ['platform' => 'linkedin', 'content' => str_repeat('Esempio post linkedin numero uno. ', 3)],
                ['platform' => 'instagram', 'content' => str_repeat('Esempio post instagram numero due. ', 3)],
                ['platform' => 'facebook', 'content' => str_repeat('Esempio post facebook numero tre. ', 3)],
            ],
            'narrative_assets' => [
                ['type' => 'course', 'name' => 'PRIMUS', 'details' => '4 ore'],
                ['type' => 'book', 'name' => 'Restare Umani'],
            ],
            'unique_selling_points' => 'Approccio etico, percorsi modulari',
            'brand_values'          => ['etica', 'concretezza'],
            'default_content_pillars' => [
                ['name' => 'Frontiera tecnica',   'description' => 'Aggiornamenti tecnici'],
                ['name' => 'Pattern operativi',   'description' => 'Pattern di implementazione'],
                ['name' => 'Posizione contrarian','description' => 'Tesi controcorrente'],
                ['name' => 'Manifesto',           'description' => 'Valori'],
            ],
        ]);

        // Aggiungi un BrandDocument (KB +15)
        BrandDocument::create([
            'brand_id'           => $brand->id,
            'filename'           => 'doc1.pdf',
            'original_filename'  => 'doc1.pdf',
            'file_type'          => 'pdf',
            'file_size'          => 1024,
            'file_path'          => 'docs/doc1.pdf',
            'extraction_status'  => 'pending',
            'analysis_status'    => 'pending',
        ]);

        $result = $this->service->score($brand->refresh());

        expect($result['score'])->toBe(100)
            ->and($result['can_generate'])->toBeTrue()
            ->and($result['sections']['narrative_assets']['complete'])->toBeTrue()
            ->and($result['sections']['usp_pillars']['complete'])->toBeTrue()
            ->and($result['sections']['usp_pillars']['earned'])->toBe(25)
            ->and($result['sections']['kb']['complete'])->toBeTrue()
            ->and($result['missing'])->toBeEmpty();
    });

    it('voice_examples a null è trattato come empty (sezione voice 0)', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org, [
            'name'           => 'Acme SRL',
            'description'    => str_repeat('Descrizione completa del brand per onboarding wizard. ', 3),
            'sector'         => 'Tech',
            'tone_of_voice'  => 'professionale',
            'voice_examples' => null,
        ]);

        $result = $this->service->score($brand);

        expect($result['score'])->toBe(15) // solo identity
            ->and($result['sections']['voice']['earned'])->toBe(0)
            ->and($result['sections']['voice']['complete'])->toBeFalse();
    });

    it('default_content_pillars con 3 entries (sotto soglia 4) → usp_pillars solo 10 (USP+values pieni, pillars 0)', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org, [
            'name'         => 'Acme SRL',
            'sector'       => 'Tech',
            'description'  => null,                        // identity incomplete (forziamo isolato)
            'unique_selling_points' => 'USP completo',
            'brand_values'          => ['v1', 'v2', 'v3'], // ≥2 ok
            'default_content_pillars' => [
                ['name' => 'P1', 'description' => 'd1'],
                ['name' => 'P2', 'description' => 'd2'],
                ['name' => 'P3', 'description' => 'd3'],   // solo 3 — sotto soglia 4
            ],
        ]);

        $result = $this->service->score($brand);

        // USP+values OK (10pt), pillars sotto soglia (0pt) → usp_pillars 10/25
        expect($result['sections']['usp_pillars']['earned'])->toBe(10)
            ->and($result['sections']['usp_pillars']['complete'])->toBeFalse();

        // Tra i missing c'è il messaggio sui pillar
        $missingLabels = array_column($result['missing'], 'label');
        expect(implode("\n", $missingLabels))->toContain('Pillar: serve min 4');
    });
});
