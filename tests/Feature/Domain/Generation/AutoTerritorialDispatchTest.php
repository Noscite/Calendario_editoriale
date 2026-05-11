<?php

declare(strict_types=1);

use App\Domain\Brand\Exceptions\MissingBrandApiKeyException;
use App\Domain\Generation\Contracts\ContentGeneratorInterface;
use App\Domain\Generation\Jobs\GenerateCalendarJob;
use App\Domain\Project\Enums\ProjectStatus;
use App\Domain\Territorial\Jobs\GenerateTerritorialPostsJob;
use App\Domain\Territorial\Jobs\SyncTerritorialEventsJob;
use Illuminate\Support\Facades\Bus;

function fakeContentGenerator(?\Throwable $generateThrows = null): ContentGeneratorInterface
{
    $mock = Mockery::mock(ContentGeneratorInterface::class)->shouldIgnoreMissing();

    $mock->shouldReceive('useBrandKeys')->andReturnNull();

    if ($generateThrows !== null) {
        $mock->shouldReceive('generateCalendarPosts')->andThrow($generateThrows);
    } else {
        // Ritorno triplo richiesto dal job: [posts, updatedPersonas, tokensUsed]
        $mock->shouldReceive('generateCalendarPosts')->andReturn([[], [], 0]);
    }

    return $mock;
}

it('dispatches territorial chain after calendar generation for unpli_regional brand', function () {
    Bus::fake([SyncTerritorialEventsJob::class, GenerateTerritorialPostsJob::class]);

    [, $org] = createAuthenticatedUser();
    $brand = createBrand($org, ['vertical' => 'unpli_regional']);
    $project = createProject($brand, ['status' => ProjectStatus::Generating->value]);

    (new GenerateCalendarJob($project->id))->handle(fakeContentGenerator(), app(\App\Domain\Generation\Services\GenerationProgressService::class));

    Bus::assertChained([
        SyncTerritorialEventsJob::class,
        GenerateTerritorialPostsJob::class,
    ]);
});

it('dispatches territorial chain for pro_loco brand', function () {
    Bus::fake([SyncTerritorialEventsJob::class, GenerateTerritorialPostsJob::class]);

    [, $org] = createAuthenticatedUser();
    $brand = createBrand($org, ['vertical' => 'pro_loco']);
    $project = createProject($brand, ['status' => ProjectStatus::Generating->value]);

    (new GenerateCalendarJob($project->id))->handle(fakeContentGenerator(), app(\App\Domain\Generation\Services\GenerationProgressService::class));

    Bus::assertChained([
        SyncTerritorialEventsJob::class,
        GenerateTerritorialPostsJob::class,
    ]);
});

it('does not dispatch territorial chain for brand without territorial vertical', function () {
    Bus::fake([SyncTerritorialEventsJob::class, GenerateTerritorialPostsJob::class]);

    [, $org] = createAuthenticatedUser();
    $brand = createBrand($org, ['vertical' => null]);
    $project = createProject($brand, ['status' => ProjectStatus::Generating->value]);

    (new GenerateCalendarJob($project->id))->handle(fakeContentGenerator(), app(\App\Domain\Generation\Services\GenerationProgressService::class));

    Bus::assertNotDispatched(SyncTerritorialEventsJob::class);
    Bus::assertNotDispatched(GenerateTerritorialPostsJob::class);
});

it('does not dispatch territorial chain when calendar generation throws missing api key', function () {
    Bus::fake([SyncTerritorialEventsJob::class, GenerateTerritorialPostsJob::class]);

    [, $org] = createAuthenticatedUser();
    $brand = createBrand($org, ['vertical' => 'unpli_regional']);
    $project = createProject($brand, ['status' => ProjectStatus::Generating->value]);

    $generator = fakeContentGenerator(
        new MissingBrandApiKeyException('anthropic_api_key', $brand->name)
    );

    // useBrandKeys lancia l'exception nel codice reale, ma per il test
    // forziamo generateCalendarPosts a lanciarla. Il blocco try/catch del job
    // intercetta, chiama $this->fail(...) e setta status=draft.
    try {
        (new GenerateCalendarJob($project->id))->handle($generator, app(\App\Domain\Generation\Services\GenerationProgressService::class));
    } catch (\Throwable) {
        // ignorato: il job non rilancia (fa $this->fail)
    }

    Bus::assertNotDispatched(SyncTerritorialEventsJob::class);
    Bus::assertNotDispatched(GenerateTerritorialPostsJob::class);
});
