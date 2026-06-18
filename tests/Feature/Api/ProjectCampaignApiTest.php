<?php

declare(strict_types=1);

use App\Domain\Brand\Models\Brand;
use App\Domain\Campaign\Enums\CampaignStatus;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Models\CampaignAttachment;
use App\Domain\Document\Models\BrandDocument;
use App\Domain\Generation\Jobs\GenerateCampaignPostsJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * Crea un documento KB completato per un brand (no factory dedicata).
 */
function createKbDocument(Brand $brand, array $overrides = []): BrandDocument
{
    return BrandDocument::create(array_merge([
        'brand_id'          => $brand->id,
        'filename'          => 'doc-' . \Illuminate\Support\Str::random(6) . '.pdf',
        'original_filename' => 'doc.pdf',
        'file_type'         => 'pdf',
        'file_path'         => 'brand-documents/doc.pdf',
        'file_size'         => 1234,
        'analysis_status'   => 'completed',
        'summary'           => 'Riassunto del documento',
    ], $overrides));
}

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

// ── KB documents: selezione persistita anche sul path di generazione immediata ──

it('store (immediate generation) popola la pivot KB PRIMA del dispatch', function () {
    $doc = createKbDocument($this->brand);

    $response = $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/campaigns", [
            'name'            => 'Con KB',
            'brief'           => 'Brief con documenti KB',
            'brand_documents' => [['id' => $doc->id, 'inject_mode' => 'full_text']],
        ]);

    $response->assertCreated();
    $campaignId = $response->json('id');

    // La pivot è popolata: il sync avviene dentro la transazione di
    // createAndGenerate, PRIMA del dispatch → il job vede già i documenti.
    $pivot = DB::table('campaign_brand_document')->where('campaign_id', $campaignId)->get();
    expect($pivot)->toHaveCount(1);
    expect($pivot->first()->brand_document_id)->toBe($doc->id);
    expect($pivot->first()->inject_mode)->toBe('full_text');

    // E il job di generazione è stato comunque dispatchato (dopo il sync).
    Queue::assertPushed(GenerateCampaignPostsJob::class);
});

it('store scarta documenti KB di un\'altra organization (anti-IDOR)', function () {
    [, $otherOrg] = createAuthenticatedUser();
    $otherBrand   = createBrand($otherOrg);
    $foreignDoc   = createKbDocument($otherBrand);
    $ownDoc       = createKbDocument($this->brand);

    $response = $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/campaigns", [
            'name'            => 'IDOR test',
            'brief'           => 'Brief',
            'brand_documents' => [
                ['id' => $foreignDoc->id, 'inject_mode' => 'full_text'],
                ['id' => $ownDoc->id,     'inject_mode' => 'summary'],
            ],
        ]);

    $response->assertCreated();
    $campaignId = $response->json('id');

    // Solo il documento dell'organization dell'utente finisce in pivot;
    // quello di un altro tenant è scartato dal filtro org.
    $docIds = DB::table('campaign_brand_document')
        ->where('campaign_id', $campaignId)
        ->pluck('brand_document_id')
        ->all();

    expect($docIds)->toBe([$ownDoc->id]);
    expect($docIds)->not->toContain($foreignDoc->id);
});

it('store e promote producono la stessa pivot KB a parità di payload', function () {
    $doc     = createKbDocument($this->brand);
    $payload = [['id' => $doc->id, 'inject_mode' => 'full_text']];

    // Path 1: store (generazione immediata)
    $storeRes = $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/campaigns", [
            'name'            => 'Via store',
            'brief'           => 'Brief',
            'brand_documents' => $payload,
        ]);
    $storeRes->assertCreated();

    // Path 2: draft → promote
    $draft = Campaign::create([
        'organization_id' => $this->org->id,
        'name'            => 'Bozza',
        'brief'           => '',
        'status'          => CampaignStatus::Draft,
    ]);
    $promoteRes = $this->actingAs($this->user)
        ->postJson("/api/projects/{$this->project->id}/campaigns/{$draft->id}/promote", [
            'name'            => 'Via promote',
            'brief'           => 'Brief',
            'brand_documents' => $payload,
        ]);
    $promoteRes->assertOk();

    $storePivot = DB::table('campaign_brand_document')
        ->where('campaign_id', $storeRes->json('id'))
        ->pluck('inject_mode', 'brand_document_id')
        ->toArray();

    $promotePivot = DB::table('campaign_brand_document')
        ->where('campaign_id', $draft->id)
        ->pluck('inject_mode', 'brand_document_id')
        ->toArray();

    expect($storePivot)->toBe([$doc->id => 'full_text']);
    expect($promotePivot)->toBe($storePivot);
});
