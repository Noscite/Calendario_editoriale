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
            ->and($post->generation_metadata['model_copy'])->toBe('claude-opus-4-8')
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
                'model_copy'     => 'claude-opus-4-8',
            ]);

        // Inserimento reale via Post::insert() per verificare end-to-end
        Post::insert([$row]);
        $persisted = Post::withoutGlobalScope('organization')
            ->where('project_id', $project->id)->latest('id')->first();

        expect($persisted)->not->toBeNull();
        expect($persisted->generation_metadata)->toBeArray()
            ->and($persisted->generation_metadata['model_copy'])->toBe('claude-opus-4-8');
    });

    it('è idempotente con $raw vuoto: metadata sempre valido, nessun crash', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand   = createBrand($org);
        $project = createProject($brand);

        $gen = app(ClaudeContentGenerator::class);

        $row = $gen->buildPostRow([], $project->id, $org->id, [], null, false);

        expect($row['status'])->toBe('draft')
            ->and($row['generation_metadata'])->toBeArray()
            ->and($row['generation_metadata']['model_copy'])->toBe('claude-opus-4-8')
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

    // ── Pillar coercion (matchPillar) ───────────────────────────────

    it('pillar exact match → status exact, niente coercion né log', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand   = createBrand($org);
        $project = createProject($brand, [
            'content_pillars' => ['Frontiera tecnica', 'Pattern operativi'],
        ]);

        Log::spy();

        $gen = app(ClaudeContentGenerator::class);
        $row = $gen->buildPostRow(
            raw: ['platform' => 'instagram', 'pillar' => 'Pattern operativi'],
            projectId: $project->id,
            organizationId: $org->id,
            projectContentPillars: $project->content_pillars,
            forBulkInsert: false,
        );

        expect($row['pillar'])->toBe('Pattern operativi')
            ->and($row['generation_metadata']['pillar_normalized_from'])->toBeNull()
            ->and($row['generation_metadata']['pillar_invented'])->toBeNull();

        Log::shouldNotHaveReceived('warning');
        Log::shouldNotHaveReceived('info');
    });

    it('pillar case mismatch → status normalized, coerced al casing canonico, log INFO', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand   = createBrand($org);
        $project = createProject($brand, [
            'content_pillars' => ['Frontiera tecnica', 'Pattern operativi', 'Posizione contrarian'],
        ]);

        Log::spy();

        $gen = app(ClaudeContentGenerator::class);
        $row = $gen->buildPostRow(
            raw: ['platform' => 'instagram', 'pillar' => 'frontiera tecnica'],
            projectId: $project->id,
            organizationId: $org->id,
            projectContentPillars: $project->content_pillars,
            forBulkInsert: false,
        );

        // Coerce al casing canonico
        expect($row['pillar'])->toBe('Frontiera tecnica')
            ->and($row['generation_metadata']['pillar_normalized_from'])->toBe('frontiera tecnica')
            ->and($row['generation_metadata']['pillar_invented'])->toBeNull();

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(function (string $msg, array $ctx): bool {
                return str_contains($msg, 'Pillar normalizzato')
                    && $ctx['pillar_original']  === 'frontiera tecnica'
                    && $ctx['pillar_canonical'] === 'Frontiera tecnica';
            });
        Log::shouldNotHaveReceived('warning');
    });

    it('pillar slug mismatch → status normalized (anche con accenti diversi)', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand   = createBrand($org);
        $project = createProject($brand, [
            'content_pillars' => ['Conformità AI Act', 'Mappatura percorso'],
        ]);

        Log::spy();

        $gen = app(ClaudeContentGenerator::class);
        $row = $gen->buildPostRow(
            raw: ['platform' => 'linkedin', 'pillar' => 'conformita_ai_act'],
            projectId: $project->id,
            organizationId: $org->id,
            projectContentPillars: $project->content_pillars,
            forBulkInsert: false,
        );

        expect($row['pillar'])->toBe('Conformità AI Act')
            ->and($row['generation_metadata']['pillar_normalized_from'])->toBe('conformita_ai_act')
            ->and($row['generation_metadata']['pillar_invented'])->toBeNull();

        Log::shouldHaveReceived('info')->once();
        Log::shouldNotHaveReceived('warning');
    });

    it('pillar inventato → status invented, valore mantenuto, log WARNING e tracciato in metadata', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand   = createBrand($org);
        $project = createProject($brand, [
            'content_pillars' => ['Frontiera tecnica', 'Pattern operativi', 'Posizione contrarian'],
        ]);

        Log::spy();

        $gen = app(ClaudeContentGenerator::class);
        $row = $gen->buildPostRow(
            raw: ['platform' => 'instagram', 'pillar' => 'thought leadership'],
            projectId: $project->id,
            organizationId: $org->id,
            projectContentPillars: $project->content_pillars,
            forBulkInsert: false,
        );

        // Mantieni il valore inventato (debt visibile, no fallback aggressivo)
        expect($row['pillar'])->toBe('thought leadership')
            ->and($row['generation_metadata']['pillar_normalized_from'])->toBeNull()
            ->and($row['generation_metadata']['pillar_invented'])->toBe('thought leadership');

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $msg, array $ctx) use ($project): bool {
                return str_contains($msg, 'Pillar fuori da content_pillars')
                    && $ctx['project_id']      === $project->id
                    && $ctx['pillar_proposed'] === 'thought leadership';
            });
    });
});
