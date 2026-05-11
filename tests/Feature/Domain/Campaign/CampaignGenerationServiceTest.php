<?php

declare(strict_types=1);

use App\Domain\Campaign\Enums\CampaignStatus;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Models\CampaignAttachment;
use App\Domain\Campaign\Services\CampaignGenerationService;
use App\Domain\Generation\Jobs\GenerateCampaignPostsJob;
use App\Domain\Mcp\Models\CampaignMcpServer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    [$this->user, $this->org] = createAuthenticatedUser();
    $this->brand = createBrand($this->org);
    $this->project = createProject($this->brand, [
        'platforms'  => ['instagram', 'linkedin'],
        'start_date' => '2026-06-01',
        'end_date'   => '2026-06-30',
    ]);
    $this->service = app(CampaignGenerationService::class);
});

afterEach(function () {
    foreach (Campaign::all() as $c) {
        $dir = storage_path("app/campaign-attachments/{$c->id}");
        if (is_dir($dir)) {
            foreach (glob($dir . '/*') as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
    }
});

it('creates Campaign in Planning state, dispatches job, links brand', function () {
    $campaign = $this->service->createAndGenerate($this->project, [
        'name'        => 'Lancio prodotto X',
        'brief'       => 'Comunichiamo il lancio del prodotto X premium',
        'pillar'      => 'Educational',
        'platforms'   => ['instagram'],
        'posts_count' => 5,
    ], $this->user->id);

    expect($campaign->status)->toBe(CampaignStatus::Planning);
    expect($campaign->name)->toBe('Lancio prodotto X');
    expect($campaign->pillar)->toBe('Educational');
    expect($campaign->organization_id)->toBe($this->org->id);
    expect($campaign->created_by_user_id)->toBe($this->user->id);

    // Brand linkato via pivot brand_campaign
    expect($campaign->brands()->where('brands.id', $this->brand->id)->exists())->toBeTrue();

    Queue::assertPushed(GenerateCampaignPostsJob::class, function ($job) use ($campaign) {
        return $job->campaignId === $campaign->id
            && $job->projectId === $this->project->id
            && $job->platformsRequested === ['instagram']
            && $job->postsCountRequested === 5;
    });
});

it('stores attachments + dispatches ExtractAttachmentTextJob', function () {
    $file = UploadedFile::fake()->createWithContent('brief.txt', 'Test content');

    $campaign = $this->service->createAndGenerate($this->project, [
        'name'        => 'Test campaign',
        'brief'       => 'Test brief',
        'attachments' => [$file],
    ], $this->user->id);

    expect($campaign->attachments()->count())->toBe(1);
    $attachment = $campaign->attachments()->first();
    expect($attachment->original_filename)->toBe('brief.txt');
    expect($attachment->extraction_status)->toBe('pending');

    Queue::assertPushed(\App\Domain\Campaign\Jobs\ExtractAttachmentTextJob::class);
});

it('stores MCP servers with encrypted API key', function () {
    $campaign = $this->service->createAndGenerate($this->project, [
        'name'        => 'With MCP',
        'brief'       => 'Brief con MCP',
        'mcp_servers' => [
            ['name' => 'Catalogo', 'url' => 'https://mcp.example.com/sse', 'api_key' => 'super-secret'],
        ],
    ], $this->user->id);

    $mcp = CampaignMcpServer::where('campaign_id', $campaign->id)->first();
    expect($mcp)->not->toBeNull();
    expect($mcp->name)->toBe('Catalogo');
    expect($mcp->getApiKey())->toBe('super-secret');
    expect($mcp->encrypted_api_key)->not->toBe('super-secret');
});

it('passes null platforms/count when AI-decide mode', function () {
    $campaign = $this->service->createAndGenerate($this->project, [
        'name'  => 'AI decide',
        'brief' => 'Lascia decidere all\'AI',
        // platforms + posts_count omitted
    ], $this->user->id);

    Queue::assertPushed(GenerateCampaignPostsJob::class, function ($job) use ($campaign) {
        return $job->campaignId === $campaign->id
            && $job->platformsRequested === null
            && $job->postsCountRequested === null;
    });
});

it('promoteDraft updates status from Draft to Planning + dispatches job', function () {
    $draft = Campaign::create([
        'organization_id' => $this->org->id,
        'name'            => 'Bozza',
        'brief'           => '',
        'status'          => CampaignStatus::Draft,
    ]);

    $promoted = $this->service->promoteDraft($draft, $this->project, [
        'name'      => 'Finalizzata',
        'brief'     => 'Brief completo',
        'pillar'    => 'Educational',
        'platforms' => ['linkedin'],
    ]);

    $promoted->refresh();
    expect($promoted->status)->toBe(CampaignStatus::Planning);
    expect($promoted->name)->toBe('Finalizzata');
    expect($promoted->brief)->toBe('Brief completo');

    Queue::assertPushed(GenerateCampaignPostsJob::class);
});
