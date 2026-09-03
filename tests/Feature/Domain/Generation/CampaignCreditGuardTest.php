<?php

declare(strict_types=1);

use App\Domain\Campaign\Enums\CampaignStatus;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Generation\Jobs\GenerateCampaignPostsJob;
use App\Domain\Generation\Services\ClaudeContentGenerator;
use App\Domain\Subscription\Services\PostCreditService;
use Illuminate\Support\Facades\Http;

/**
 * Le campagne non passano dalla preflight del calendario principale
 * (GenerationController::preflight) — GenerateCampaignPostsJob deve
 * ripetere il controllo credito da solo prima di spendere token AI.
 */
beforeEach(function () {
    config()->set('services.anthropic.api_key', 'test-key');

    [, $this->org] = createAuthenticatedUser();
    $this->brand   = createBrand($this->org, ['name' => 'CreditGuardBrand']);
    $this->project = createProject($this->brand, [
        'platforms'  => ['linkedin'],
        'start_date' => '2026-06-01',
        'end_date'   => '2026-06-14',
    ]);
    $this->campaign = Campaign::create([
        'organization_id' => $this->org->id,
        'name'            => 'Campagna senza credito',
        'brief'           => 'Brief',
        'status'          => CampaignStatus::Planning,
    ]);
});

it('blocks the job before calling Anthropic when the org is enrolled and short on credit', function () {
    app(PostCreditService::class)->credit($this->org->id, 1, 'purchase');

    Http::fake(); // qualunque chiamata qui sarebbe un fallimento del test

    $job = new GenerateCampaignPostsJob(
        campaignId: $this->campaign->id,
        projectId: $this->project->id,
        platformsRequested: ['linkedin'],
        postsCountRequested: 10, // richiede più post di quanti credito ne copra
    );
    $job->handle(app(ClaudeContentGenerator::class));

    Http::assertNothingSent();

    $this->campaign->refresh();
    expect($this->campaign->status)->toBe(CampaignStatus::Draft);
    expect($this->campaign->generation_error)->toContain('Credito insufficiente');
});

it('does not block the job for a non-enrolled organization', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode([])]],
            'usage'   => ['input_tokens' => 10, 'output_tokens' => 10],
        ], 200),
    ]);

    $job = new GenerateCampaignPostsJob(
        campaignId: $this->campaign->id,
        projectId: $this->project->id,
        platformsRequested: ['linkedin'],
        postsCountRequested: 10,
    );
    $job->handle(app(ClaudeContentGenerator::class));

    Http::assertSent(fn ($request) => str_contains($request->url(), 'api.anthropic.com'));
});
