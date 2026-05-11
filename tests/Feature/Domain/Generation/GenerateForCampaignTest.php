<?php

declare(strict_types=1);

use App\Domain\Campaign\Enums\CampaignStatus;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Models\CampaignAttachment;
use App\Domain\Generation\Services\ClaudeContentGenerator;
use App\Domain\Post\Models\Post;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.anthropic.api_key', 'test-key');
    config()->set('services.anthropic.strategy_split', false);  // forza legacy batch path (più semplice da mockare)

    [$this->user, $this->org] = createAuthenticatedUser();
    $this->brand   = createBrand($this->org, ['name' => 'TestBrand']);
    $this->project = createProject($this->brand, [
        'platforms'  => ['linkedin', 'instagram'],
        'start_date' => '2026-06-01',
        'end_date'   => '2026-06-14',
        'themes'     => ['Educational'],
    ]);
});

afterEach(function () {
    foreach (Campaign::all() as $c) {
        $dir = storage_path("app/campaign-attachments/{$c->id}");
        if (is_dir($dir)) {
            foreach (glob($dir . '/*') as $file) @unlink($file);
            @rmdir($dir);
        }
    }
});

it('creates posts with campaign_id valorized', function () {
    $campaign = Campaign::create([
        'organization_id' => $this->org->id,
        'name'            => 'Test campagna',
        'brief'           => 'Brief campagna',
        'pillar'          => 'Educational',
        'status'          => CampaignStatus::Planning,
    ]);

    // Mock Anthropic response: 1 post per platform
    Http::fake([
        'api.anthropic.com/*' => Http::sequence()
            // Le chiamate del batch legacy ritornano un array di post JSON
            ->push([
                'content' => [['type' => 'text', 'text' => json_encode([
                    [
                        'scheduled_date'  => '2026-06-05',
                        'scheduled_time'  => '09:00',
                        'platform'        => 'linkedin',
                        'content'         => 'Post linkedin campagna',
                        'hashtags'        => ['#test'],
                        'pillar'          => 'Educational',
                        'cta'             => 'Scopri di più',
                    ],
                    [
                        'scheduled_date'  => '2026-06-06',
                        'scheduled_time'  => '12:00',
                        'platform'        => 'instagram',
                        'content'         => 'Post instagram campagna',
                        'hashtags'        => ['#test'],
                        'pillar'          => 'Educational',
                        'cta'             => 'Scopri di più',
                    ],
                ])]],
                'usage'   => ['input_tokens' => 100, 'output_tokens' => 200],
            ])
            // Image prompts (Haiku) richieste dopo il batch — risposta dummy
            ->whenEmpty(Http::response([
                'content' => [['type' => 'text', 'text' => 'image prompt dummy']],
                'usage'   => ['input_tokens' => 10, 'output_tokens' => 5],
            ], 200)),
    ]);

    $generator = app(ClaudeContentGenerator::class);
    $generator->generateForCampaign(
        campaign:   $campaign,
        project:    $this->project,
        platforms:  ['linkedin', 'instagram'],
        postsCount: 2,
    );

    $posts = Post::where('project_id', $this->project->id)
        ->where('campaign_id', $campaign->id)
        ->get();

    expect($posts->count())->toBeGreaterThan(0);
    foreach ($posts as $p) {
        expect($p->campaign_id)->toBe($campaign->id);
        expect($p->project_id)->toBe($this->project->id);
    }
});

it('injects campaign KB into prompt when attachments are ready (strategy_split path)', function () {
    // KB injection vive nel buildStrategyPrompt → strategy_split=true required
    config()->set('services.anthropic.strategy_split', true);

    $campaign = Campaign::create([
        'organization_id' => $this->org->id,
        'name'            => 'KB campaign',
        'brief'           => 'Brief',
        'status'          => CampaignStatus::Planning,
    ]);

    CampaignAttachment::create([
        'campaign_id'       => $campaign->id,
        'original_filename' => 'specs.txt',
        'stored_filename'   => 'specs.txt',
        'mime_type'         => 'text/plain',
        'size_bytes'        => 50,
        'extraction_status' => 'completed',
        'extracted_text'    => 'Il prodotto X ha una shelf life di 12 mesi.',
        'extracted_at'      => now(),
    ]);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['posts' => []])]],
            'usage'   => ['input_tokens' => 100, 'output_tokens' => 50],
        ], 200),
    ]);

    $generator = app(ClaudeContentGenerator::class);
    // try/catch perché la mock response empty manda in errore il post-processing
    // downstream (array_push named args) — non rilevante per questo test che
    // verifica solo lo SHAPE del prompt outgoing.
    try {
        $generator->generateForCampaign(
            campaign:   $campaign,
            project:    $this->project,
            platforms:  ['linkedin'],
            postsCount: 1,
        );
    } catch (\Throwable) {
        // Ignored: il fail post-API non blocca la verifica del request body.
    }

    Http::assertSent(function ($request) {
        $serialized = json_encode($request->data());
        return str_contains($serialized, 'shelf life di 12 mesi')
            && str_contains($serialized, 'KNOWLEDGE BASE');
    });
});
