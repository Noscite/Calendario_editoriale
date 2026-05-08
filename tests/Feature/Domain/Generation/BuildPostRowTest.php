<?php

declare(strict_types=1);

use App\Domain\Generation\Services\ClaudeContentGenerator;
use App\Domain\Post\Models\Post;
use Illuminate\Support\Facades\Log;

/**
 * Smoke test focalizzato su ClaudeContentGenerator::buildPostRow.
 *
 * Garantisce che il choke point centralizzato di costruzione delle righe
 * Post AI-generated produca generation_metadata sempre popolato (anche
 * quando il $raw è sparse) e che il warning sul pillar fuori da
 * content_pillars venga emesso correttamente.
 *
 * NOTA: il test full-stack di GenerateCalendarJob con mock Anthropic è
 * rimandato a un commit successivo (richiede setup di Http::fake con
 * payload validi per strategy plan + copy batch).
 */
describe('ClaudeContentGenerator::buildPostRow', function () {

    it('produce un Post con generation_metadata popolato (Eloquent path)', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand   = createBrand($org);
        $project = createProject($brand, [
            'content_pillars' => ['Frontiera tecnica', 'Pattern operativi'],
        ]);

        $gen = app(ClaudeContentGenerator::class);

        $row = $gen->buildPostRow(
            raw: [
                'platform'        => 'instagram',
                'scheduled_date'  => '2026-05-08',
                'scheduled_time'  => '09:00',
                'content'         => 'Test content',
                'pillar'          => 'Frontiera tecnica',
                'hashtags'        => ['#test'],
                '_strategy'       => [
                    'angle'          => 'pattern_obs',
                    'hook_type'      => 'observation',
                    'persona_target' => 'CTO',
                    'cta_goal'       => 'engagement',
                ],
                '_tokens' => ['strategy' => 100, 'copy' => 200],
            ],
            projectId: $project->id,
            organizationId: $org->id,
            projectContentPillars: $project->content_pillars,
            forBulkInsert: false,
        );

        $post = Post::create($row);
        $post->refresh();

        expect($post->generation_metadata)->toBeArray()
            ->and($post->generation_metadata['generated_at'])->toBeString()
            ->and($post->generation_metadata['model_strategy'])->toBe('claude-opus-4-7')
            ->and($post->generation_metadata['model_copy'])->toBe('claude-sonnet-4-20250514')
            ->and($post->generation_metadata['strategy_angle'])->toBe('pattern_obs')
            ->and($post->generation_metadata['tokens_copy'])->toBe(200);
    });

    it('produce un row insert-friendly con JSON string per Post::insert (bulk path)', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand   = createBrand($org);
        $project = createProject($brand);

        $gen = app(ClaudeContentGenerator::class);

        $row = $gen->buildPostRow(
            raw: [
                'platform'       => 'linkedin',
                'scheduled_date' => '2026-05-08',
                'content'        => 'Bulk',
                'hashtags'       => ['#a', '#b'],
            ],
            projectId: $project->id,
            organizationId: $org->id,
            forBulkInsert: true,
        );

        expect($row['hashtags'])->toBeString()
            ->and($row['generation_metadata'])->toBeString()
            ->and(isset($row['created_at']))->toBeTrue()
            ->and(json_decode($row['generation_metadata'], true))->toMatchArray([
                'model_strategy' => 'claude-opus-4-7',
                'model_copy'     => 'claude-sonnet-4-20250514',
            ]);

        // Inserimento reale via Post::insert() per verificare end-to-end
        Post::insert([$row]);
        $persisted = Post::withoutGlobalScope('organization')
            ->where('project_id', $project->id)->latest('id')->first();

        expect($persisted)->not->toBeNull();
        expect($persisted->generation_metadata)->toBeArray()
            ->and($persisted->generation_metadata['model_copy'])->toBe('claude-sonnet-4-20250514');
    });

    it('è idempotente con $raw vuoto: metadata sempre valido, nessun crash', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand   = createBrand($org);
        $project = createProject($brand);

        $gen = app(ClaudeContentGenerator::class);

        $row = $gen->buildPostRow([], $project->id, $org->id, [], null, false);

        expect($row['status'])->toBe('draft')
            ->and($row['generation_metadata'])->toBeArray()
            ->and($row['generation_metadata']['model_copy'])->toBe('claude-sonnet-4-20250514')
            ->and($row['generation_metadata']['strategy_angle'])->toBeNull();
    });

    it('logga warning quando il pillar proposto non è in content_pillars', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand   = createBrand($org);
        $project = createProject($brand, [
            'content_pillars' => ['Frontiera tecnica', 'Pattern operativi'],
        ]);

        Log::spy();

        $gen = app(ClaudeContentGenerator::class);
        $gen->buildPostRow(
            raw: ['platform' => 'instagram', 'pillar' => 'thought leadership'],
            projectId: $project->id,
            organizationId: $org->id,
            projectContentPillars: $project->content_pillars,
            forBulkInsert: false,
        );

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $msg, array $ctx) use ($project) {
                return str_contains($msg, 'Pillar fuori da content_pillars')
                    && $ctx['project_id'] === $project->id
                    && $ctx['pillar_proposed'] === 'thought leadership'
                    && $ctx['pillars_allowed'] === ['Frontiera tecnica', 'Pattern operativi'];
            });
    });

    it('NON logga warning quando il pillar proposto è in content_pillars', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand   = createBrand($org);
        $project = createProject($brand, [
            'content_pillars' => ['Frontiera tecnica'],
        ]);

        Log::spy();

        $gen = app(ClaudeContentGenerator::class);
        $gen->buildPostRow(
            raw: ['platform' => 'instagram', 'pillar' => 'Frontiera tecnica'],
            projectId: $project->id,
            organizationId: $org->id,
            projectContentPillars: $project->content_pillars,
            forBulkInsert: false,
        );

        Log::shouldNotHaveReceived('warning');
    });
});
