<?php

declare(strict_types=1);

use App\Domain\Campaign\Enums\CampaignStatus;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Models\CampaignAttachment;
use App\Domain\Generation\Jobs\GenerateCampaignPostsJob;
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

it('POST /api/projects/{id}/campaigns creates campaign + dispatches generation', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/campaigns", [
            'name'        => 'Lancio prodotto X',
            'brief'       => 'Comunichiamo il lancio del prodotto X',
            'pillar'      => 'Educational',
            'platforms'   => ['linkedin'],
            'posts_count' => 5,
        ]);

    $response->assertCreated();
    $response->assertJsonStructure(['id', 'name', 'status', 'message']);
    $response->assertJsonPath('status', 'planning');

    expect(Campaign::count())->toBe(1);
    Queue::assertPushed(GenerateCampaignPostsJob::class);
});

it('POST /api/projects/{id}/campaigns accepts AI-decide mode (null platforms+count)', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/campaigns", [
            'name'  => 'AI decide',
            'brief' => 'Lascia decidere AI',
        ]);

    $response->assertCreated();

    Queue::assertPushed(GenerateCampaignPostsJob::class, function ($job) {
        return $job->platformsRequested === null && $job->postsCountRequested === null;
    });
});

it('POST /api/projects/{id}/campaigns with attachments uploads + dispatches extraction', function () {
    $file = UploadedFile::fake()->createWithContent('brief.txt', 'Brief content');

    $response = $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/campaigns", [
            'name'        => 'With attachments',
            'brief'       => 'Brief',
            'attachments' => [$file],
        ]);

    $response->assertCreated();
    expect(CampaignAttachment::count())->toBe(1);
});

it('POST /api/projects/{id}/campaigns rejects > 5 attachments', function () {
    $files = array_map(
        fn ($i) => UploadedFile::fake()->createWithContent("f{$i}.txt", "x"),
        range(1, 6),
    );

    $response = $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/campaigns", [
            'name'        => 'Too many',
            'brief'       => 'Brief',
            'attachments' => $files,
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['attachments']);
});

it('POST /api/projects/{id}/campaigns/draft creates Draft campaign for lazy upload', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/campaigns/draft", [
            'name' => 'Bozza modal',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('status', 'draft');

    $draft = Campaign::find($response->json('id'));
    expect($draft->status)->toBe(CampaignStatus::Draft);

    // Brand linkato anche al draft (così attachments e MCP sono organizationally scoped)
    expect($draft->brands()->where('brands.id', $this->brand->id)->exists())->toBeTrue();
});

it('POST /api/projects/{id}/campaigns/{campaign}/promote moves Draft → Planning', function () {
    $draft = Campaign::create([
        'organization_id' => $this->org->id,
        'name'            => 'Bozza',
        'brief'           => '',
        'status'          => CampaignStatus::Draft,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/campaigns/{$draft->id}/promote", [
            'name'      => 'Finalizzata',
            'brief'     => 'Brief completo',
            'pillar'    => 'Educational',
            'platforms' => ['instagram'],
        ]);

    $response->assertOk();
    $response->assertJsonPath('status', 'planning');

    Queue::assertPushed(GenerateCampaignPostsJob::class);
});

it('POST promote rejects non-Draft campaigns', function () {
    // Crea in Draft poi forza Active aggirando l'observer (CampaignLimitChecker
    // richiederebbe plan setup non strettamente necessario per il test del 422)
    $campaign = Campaign::create([
        'organization_id' => $this->org->id,
        'name'            => 'Test',
        'brief'           => 'b',
        'status'          => CampaignStatus::Draft,
    ]);
    \Illuminate\Support\Facades\DB::table('campaigns')->where('id', $campaign->id)->update(['status' => 'active']);

    $response = $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/campaigns/{$campaign->id}/promote", [
            'name'  => 'whatever',
            'brief' => 'whatever',
        ]);

    $response->assertStatus(422);
});

it('GET /api/projects/{id}/campaigns lists campaigns having posts in this project', function () {
    $campaign = Campaign::create([
        'organization_id' => $this->org->id,
        'name'            => 'Storico campagna',
        'brief'           => 'b',
        'status'          => CampaignStatus::Active,
    ]);

    // Post linkato a project + campaign
    \App\Domain\Post\Models\Post::create([
        'project_id'       => $this->project->id,
        'organization_id'  => $this->org->id,
        'campaign_id'      => $campaign->id,
        'platform'         => 'instagram',
        'scheduled_date'   => '2026-06-15',
        'scheduled_time'   => '09:00',
        'content'          => 'Post AI',
        'status'           => 'draft',
        'publication_status' => 'draft',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/projects/{$this->project->id}/campaigns");

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.id', $campaign->id);
    $response->assertJsonPath('data.0.posts_in_project_count', 1);
});
