<?php

declare(strict_types=1);

use App\Domain\Brand\Models\Brand;
use App\Domain\Document\Models\BrandDocument;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    // Evita il dispatch sync di BootstrapBrandOntologyJob (osservatore Brand).
    Bus::fake();
});

describe('GET /api/brands/{brand}/completeness', function () {

    it('senza auth ritorna 401', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org);

        $response = $this->getJson("/api/brands/{$brand->id}/completeness");

        $response->assertStatus(401);
    });

    it('su brand di altra org ritorna 404 (org scoping via scopeBindings)', function () {
        [$user1, $org1] = createAuthenticatedUser();
        [$user2, $org2] = createAuthenticatedUser();
        $brandOfOtherOrg = createBrand($org2);

        $response = $this->actingAs($user1)
            ->getJson("/api/brands/{$brandOfOtherOrg->id}/completeness");

        $response->assertStatus(404);
    });

    it('su brand pieno ritorna shape completa con score 100', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand = createBrand($org, [
            'name'           => 'Acme SRL',
            'description'    => str_repeat('Descrizione del brand per test wizard. ', 4),
            'sector'         => 'Tech',
            'tone_of_voice'  => 'professionale',
            'voice_examples' => [
                ['platform' => 'linkedin', 'content' => str_repeat('Esempio post linkedin numero uno. ', 3)],
                ['platform' => 'instagram', 'content' => str_repeat('Esempio post instagram numero due. ', 3)],
                ['platform' => 'facebook', 'content' => str_repeat('Esempio post facebook numero tre. ', 3)],
            ],
            'narrative_assets' => [
                ['type' => 'course', 'name' => 'PRIMUS', 'details' => '4 ore'],
            ],
            'unique_selling_points'   => 'USP completo',
            'brand_values'            => ['v1', 'v2'],
            'default_content_pillars' => [
                ['name' => 'P1', 'description' => 'd1'],
                ['name' => 'P2', 'description' => 'd2'],
                ['name' => 'P3', 'description' => 'd3'],
                ['name' => 'P4', 'description' => 'd4'],
            ],
        ]);
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

        $response = $this->actingAs($user)
            ->getJson("/api/brands/{$brand->id}/completeness");

        $response->assertOk()
            ->assertJson([
                'score'        => 100,
                'threshold'    => 70,
                'can_generate' => true,
            ])
            ->assertJsonStructure([
                'score', 'threshold', 'can_generate',
                'sections' => [
                    'identity'         => ['weight', 'earned', 'complete', 'label'],
                    'voice'            => ['weight', 'earned', 'complete', 'label'],
                    'narrative_assets' => ['weight', 'earned', 'complete', 'label'],
                    'usp_pillars'      => ['weight', 'earned', 'complete', 'label'],
                    'kb'               => ['weight', 'earned', 'complete', 'label'],
                ],
                'missing',
            ]);

        expect($response->json('missing'))->toBeEmpty();
    });
});
