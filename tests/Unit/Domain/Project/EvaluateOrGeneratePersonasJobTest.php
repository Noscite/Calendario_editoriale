<?php

declare(strict_types=1);

use App\Domain\Document\Contracts\OpenAiEmbeddingClientInterface;
use App\Domain\Generation\Contracts\ContentGeneratorInterface;
use App\Domain\Generation\Services\PersonasEvaluationTracker;
use App\Domain\Project\Jobs\EvaluateOrGeneratePersonasJob;
use Illuminate\Support\Facades\Redis;

/**
 * Test unit per EvaluateOrGeneratePersonasJob (PR-WIZARD-2).
 *
 * Mock di ClaudeContentGenerator + OpenAiEmbeddingClient. DB usa
 * RefreshDatabase via il pest()->use più sotto.
 */

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->generator   = Mockery::mock(ContentGeneratorInterface::class);
    $this->embedClient = Mockery::mock(OpenAiEmbeddingClientInterface::class);

    // Default: useBrandKeys / withBrand sono no-op nei test
    $this->generator->shouldReceive('useBrandKeys')->andReturnNull();
    $this->embedClient->shouldReceive('withBrand')->andReturnSelf();
});

afterEach(fn () => Mockery::close());

function setupProjectWithBrandForJob(?string $brief = 'Brief nuovo project'): array
{
    [$user, $org] = createAuthenticatedUser();
    $brand   = createBrand($org, ['name' => 'Acme', 'sector' => 'Tech']);
    $project = createProject($brand, [
        'name'  => 'Project nuovo',
        'brief' => $brief,
    ]);
    return [$project, $brand];
}

describe('EvaluateOrGeneratePersonasJob — branch generate_new', function () {
    it('forceGenerateNew=true bypassa la valutazione e va a generate_new', function () {
        [$project, $brand] = setupProjectWithBrandForJob();

        $this->generator
            ->shouldReceive('generatePersonas')
            ->once()
            ->with($project->id)
            ->andReturn(['personas' => [['name' => 'Persona A']], 'source' => 'ai_analysis']);

        // Niente embedding né evaluate
        $this->embedClient->shouldNotReceive('embed');
        $this->generator->shouldNotReceive('evaluatePersonasFit');

        $job = new EvaluateOrGeneratePersonasJob($project->id, forceGenerateNew: true);
        $job->handle($this->generator, $this->embedClient);

        $project->refresh();
        expect($project->personas_source)->toBe('generated_new')
            ->and($project->personas_ai_suggestion['verdict'])->toBe('generate_new')
            ->and(PersonasEvaluationTracker::get($project->id)['status'] ?? null)->toBe('ready');
    });

    it('0 project storici → generate_new diretto', function () {
        [$project, $brand] = setupProjectWithBrandForJob();

        $this->generator
            ->shouldReceive('generatePersonas')
            ->once()
            ->andReturn(['personas' => [['name' => 'Persona A']]]);

        $this->embedClient->shouldNotReceive('embed');
        $this->generator->shouldNotReceive('evaluatePersonasFit');

        $job = new EvaluateOrGeneratePersonasJob($project->id);
        $job->handle($this->generator, $this->embedClient);

        $project->refresh();
        expect($project->personas_source)->toBe('generated_new');
    });
});

describe('EvaluateOrGeneratePersonasJob — branch reuse/adapt', function () {
    it('verdict=reuse → copia personas da source_project, source=reused_from', function () {
        [$newProject, $brand] = setupProjectWithBrandForJob('Brief nuovo simile al vecchio');
        $oldProject = createProject($brand, [
            'name'  => 'Project storico',
            'brief' => 'Brief vecchio simile',
            'buyer_personas' => [
                'personas' => [['name' => 'Vecchia persona']],
                'scheduling_strategy' => ['linkedin' => ['optimal_slots' => []]],
            ],
        ]);

        // Embedding vettori 4-dim semplici, candidato unico → top 1
        $this->embedClient
            ->shouldReceive('embed')
            ->once()
            ->andReturn([[1.0, 0.0, 0.0, 0.0], [0.95, 0.31, 0.0, 0.0]]);

        $this->generator
            ->shouldReceive('evaluatePersonasFit')
            ->once()
            ->andReturn([
                'verdict'           => 'reuse',
                'source_project_id' => $oldProject->id,
                'reasoning'         => 'Match elevato',
                'confidence'        => 0.9,
            ]);

        $this->generator->shouldNotReceive('generatePersonas');
        $this->generator->shouldNotReceive('adaptPersonas');

        $job = new EvaluateOrGeneratePersonasJob($newProject->id);
        $job->handle($this->generator, $this->embedClient);

        $newProject->refresh();
        expect($newProject->personas_source)->toBe('reused_from:' . $oldProject->id)
            ->and($newProject->buyer_personas['personas'][0]['name'])->toBe('Vecchia persona')
            ->and($newProject->buyer_personas['confirmed'])->toBeFalse()
            ->and($newProject->personas_ai_suggestion['verdict'])->toBe('reuse');
    });

    it('verdict=adapt → chiama adaptPersonas, source=adapted_from', function () {
        [$newProject, $brand] = setupProjectWithBrandForJob();
        $oldProject = createProject($brand, [
            'brief' => 'Brief storico',
            'buyer_personas' => [
                'personas' => [['name' => 'Storica']],
            ],
        ]);

        $this->embedClient
            ->shouldReceive('embed')
            ->once()
            ->andReturn([[1.0, 0.0], [0.7, 0.7]]);

        $this->generator
            ->shouldReceive('evaluatePersonasFit')
            ->once()
            ->andReturn([
                'verdict'           => 'adapt',
                'source_project_id' => $oldProject->id,
                'reasoning'         => 'Fit parziale',
                'confidence'        => 0.6,
            ]);

        $this->generator
            ->shouldReceive('adaptPersonas')
            ->once()
            ->andReturn([
                'personas' => [['name' => 'Persona rifinita']],
                'source'   => 'ai_adapted',
            ]);

        $this->generator->shouldNotReceive('generatePersonas');

        $job = new EvaluateOrGeneratePersonasJob($newProject->id);
        $job->handle($this->generator, $this->embedClient);

        $newProject->refresh();
        expect($newProject->personas_source)->toBe('adapted_from:' . $oldProject->id)
            ->and($newProject->buyer_personas['personas'][0]['name'])->toBe('Persona rifinita');
    });

    it('verdict=regenerate → fallback a generatePersonas', function () {
        [$newProject, $brand] = setupProjectWithBrandForJob();
        createProject($brand, [
            'brief'          => 'Brief storico totally diverso',
            'buyer_personas' => ['personas' => [['name' => 'X']]],
        ]);

        $this->embedClient
            ->shouldReceive('embed')
            ->once()
            ->andReturn([[1.0, 0.0], [0.0, 1.0]]);

        $this->generator
            ->shouldReceive('evaluatePersonasFit')
            ->once()
            ->andReturn([
                'verdict'           => 'regenerate',
                'source_project_id' => null,
                'reasoning'         => 'Target diverso',
                'confidence'        => 0.2,
            ]);

        $this->generator
            ->shouldReceive('generatePersonas')
            ->once()
            ->andReturn(['personas' => [['name' => 'Nuova']]]);

        $job = new EvaluateOrGeneratePersonasJob($newProject->id);
        $job->handle($this->generator, $this->embedClient);

        $newProject->refresh();
        expect($newProject->personas_source)->toBe('generated_new')
            ->and($newProject->personas_ai_suggestion['verdict'])->toBe('regenerate')
            ->and($newProject->personas_ai_suggestion['reasoning'])->toBe('Target diverso');
    });
});
