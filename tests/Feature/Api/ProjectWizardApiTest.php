<?php

declare(strict_types=1);

use App\Domain\Brand\Models\Brand;
use App\Domain\Generation\Services\PersonasEvaluationTracker;
use App\Domain\Project\Jobs\EvaluateOrGeneratePersonasJob;
use App\Domain\Project\Models\Project;
use Illuminate\Support\Facades\Queue;

/**
 * Feature test PR-WIZARD-2: nuovi endpoint AI personas + promote pillars + PATCH parziale.
 *
 * Tutti gli endpoint vivono sotto /api/projects/{id}/* (auth richiesta + org scoping).
 */

describe('POST /api/projects/{id}/evaluate-personas', function () {
    it('dispatcha EvaluateOrGeneratePersonasJob', function () {
        Queue::fake();
        [$user, $org] = createAuthenticatedUser();
        $brand   = createBrand($org);
        $project = createProject($brand, ['brief' => 'Brief test']);

        $response = $this->actingAs($user)->postJson("/api/projects/{$project->id}/evaluate-personas");

        $response->assertStatus(202)
            ->assertJsonFragment(['status' => 'evaluating']);

        Queue::assertPushed(EvaluateOrGeneratePersonasJob::class, function ($job) use ($project) {
            return readJobProperty($job, 'projectId') === $project->id;
        });
    });

    it('ritorna 409 se valutazione già in corso', function () {
        Queue::fake();
        [$user, $org] = createAuthenticatedUser();
        $brand   = createBrand($org);
        $project = createProject($brand);

        // Pre-set tracker → simula job già in corso
        PersonasEvaluationTracker::setEvaluating($project->id);

        $response = $this->actingAs($user)->postJson("/api/projects/{$project->id}/evaluate-personas");

        $response->assertStatus(409)
            ->assertJsonFragment(['status' => 'already_evaluating']);

        Queue::assertNothingPushed();

        PersonasEvaluationTracker::clear($project->id);
    });

    it('ritorna 404 per project di altra org', function () {
        Queue::fake();
        [$user]    = createAuthenticatedUser();
        $otherOrg  = \App\Domain\Organization\Models\Organization::create([
            'name' => 'Other', 'slug' => 'o-evp', 'email' => 'o-evp@test.com',
            'subscription_status' => 'active', 'is_active' => true,
        ]);
        $brand   = Brand::withoutGlobalScope('organization')->create([
            'organization_id' => $otherOrg->id, 'name' => 'X',
        ]);
        $project = createProject($brand);

        $response = $this->actingAs($user)->postJson("/api/projects/{$project->id}/evaluate-personas");

        $response->assertStatus(404);
        Queue::assertNothingPushed();
    });

    it('richiede autenticazione', function () {
        $this->postJson('/api/projects/1/evaluate-personas')->assertStatus(401);
    });
});

describe('GET /api/projects/{id}/personas-status', function () {
    it('ritorna stato evaluating dal tracker', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand   = createBrand($org);
        $project = createProject($brand);

        PersonasEvaluationTracker::setEvaluating($project->id);

        $response = $this->actingAs($user)->getJson("/api/projects/{$project->id}/personas-status");

        $response->assertOk()
            ->assertJsonFragment(['status' => 'evaluating']);

        PersonasEvaluationTracker::clear($project->id);
    });

    it('ritorna stato ready con personas + suggestion dal DB se cache scaduta', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand   = createBrand($org);
        $project = createProject($brand, [
            'buyer_personas'         => ['personas' => [['name' => 'P1']], 'confirmed' => false],
            'personas_source'        => 'reused_from:42',
            'personas_ai_suggestion' => ['verdict' => 'reuse', 'confidence' => 0.9],
        ]);

        // Tracker non settato → reconstruct from DB
        $response = $this->actingAs($user)->getJson("/api/projects/{$project->id}/personas-status");

        $response->assertOk()
            ->assertJsonFragment(['status' => 'ready'])
            ->assertJsonPath('personas_source', 'reused_from:42')
            ->assertJsonPath('personas_ai_suggestion.verdict', 'reuse')
            ->assertJsonPath('buyer_personas.personas.0.name', 'P1');
    });

    it('ritorna idle quando niente in cache e niente nel DB', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand   = createBrand($org);
        $project = createProject($brand);

        $response = $this->actingAs($user)->getJson("/api/projects/{$project->id}/personas-status");

        $response->assertOk()
            ->assertJsonFragment(['status' => 'idle']);
    });
});

describe('POST /api/projects/{id}/force-regenerate-personas', function () {
    it('dispatcha job con forceGenerateNew=true e resetta tracker', function () {
        Queue::fake();
        [$user, $org] = createAuthenticatedUser();
        $brand   = createBrand($org);
        $project = createProject($brand);

        PersonasEvaluationTracker::setReady($project->id);

        $response = $this->actingAs($user)->postJson("/api/projects/{$project->id}/force-regenerate-personas");

        $response->assertStatus(202)
            ->assertJsonFragment(['status' => 'evaluating']);

        Queue::assertPushed(EvaluateOrGeneratePersonasJob::class, function ($job) use ($project) {
            return readJobProperty($job, 'projectId') === $project->id
                && readJobProperty($job, 'forceGenerateNew') === true;
        });

        // Tracker dovrebbe essere resettato (subito dopo, il job stesso lo setterà a evaluating
        // quando handle() viene chiamato — Queue::fake non esegue handle, quindi cleared)
        expect(PersonasEvaluationTracker::get($project->id))->toBeNull();
    });
});

describe('POST /api/projects/{id}/confirm-personas', function () {
    it('marca buyer_personas come confermate', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand   = createBrand($org);
        $project = createProject($brand, [
            'buyer_personas' => ['personas' => [['name' => 'P1']], 'confirmed' => false],
        ]);

        $response = $this->actingAs($user)->postJson("/api/projects/{$project->id}/confirm-personas");

        $response->assertOk()
            ->assertJsonFragment(['status' => 'confirmed']);

        $project->refresh();
        expect($project->buyer_personas['confirmed'])->toBeTrue()
            ->and($project->buyer_personas['confirmed_at'] ?? null)->toBeString();
    });

    it('ritorna 422 se nessuna persona presente', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand   = createBrand($org);
        $project = createProject($brand);  // buyer_personas null

        $response = $this->actingAs($user)->postJson("/api/projects/{$project->id}/confirm-personas");

        $response->assertStatus(422)
            ->assertJsonFragment(['status' => 'no_personas']);
    });
});

describe('POST /api/projects/{id}/promote-pillars-to-brand', function () {
    it('chiama BrandService::mergeDefaultPillars e ritorna risultato', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand   = createBrand($org, ['default_content_pillars' => [
            ['name' => 'Esistente', 'description' => 'd'],
        ]]);
        $project = createProject($brand);

        $response = $this->actingAs($user)->postJson(
            "/api/projects/{$project->id}/promote-pillars-to-brand",
            ['pillars' => [
                ['name' => 'Nuovo A', 'description' => 'desc-a'],
                ['name' => 'esistente', 'description' => 'duplicato'],
            ]],
        );

        $response->assertOk()
            ->assertJsonFragment([
                'added_count'        => 1,
                'skipped_duplicates' => 1,
                'dropped_count'      => 0,
            ]);

        $brand->refresh();
        expect($brand->default_content_pillars)->toHaveCount(2);
    });

    it('valida payload pillars (richiesto + max 60 char name)', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand   = createBrand($org);
        $project = createProject($brand);

        $response = $this->actingAs($user)->postJson(
            "/api/projects/{$project->id}/promote-pillars-to-brand",
            ['pillars' => [['name' => str_repeat('A', 70)]]],
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['pillars.0.name']);
    });
});

describe('PATCH /api/projects/{id} (wizard PR-2)', function () {
    it('accetta payload parziale per step (solo brief)', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand   = createBrand($org);
        $project = createProject($brand, ['name' => 'Originale']);

        $response = $this->actingAs($user)->patchJson("/api/projects/{$project->id}", [
            'brief' => 'Brief aggiornato in step 1',
        ]);

        $response->assertOk();
        $project->refresh();
        expect($project->name)->toBe('Originale')   // intatto
            ->and($project->brief)->toBe('Brief aggiornato in step 1');
    });

    it('accetta personas_source + personas_ai_suggestion via PATCH', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand   = createBrand($org);
        $project = createProject($brand);

        $response = $this->actingAs($user)->patchJson("/api/projects/{$project->id}", [
            'personas_source'        => 'generated_new',
            'personas_ai_suggestion' => ['verdict' => 'generate_new', 'confidence' => 1.0],
        ]);

        $response->assertOk();
        $project->refresh();
        expect($project->personas_source)->toBe('generated_new')
            ->and($project->personas_ai_suggestion['verdict'])->toBe('generate_new');
    });
});

/**
 * Helper privato per leggere proprietà private dei Job durante asserzioni.
 */
function readJobProperty(object $obj, string $name): mixed
{
    $ref = new \ReflectionClass($obj);
    $prop = $ref->getProperty($name);
    $prop->setAccessible(true);
    return $prop->getValue($obj);
}
