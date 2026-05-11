<?php

declare(strict_types=1);

use App\Domain\Campaign\Models\CampaignAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    [$this->user, $this->org] = createAuthenticatedUser();
    $this->campaign = createCampaign($this->org);
});

afterEach(function () {
    $dir = storage_path("app/campaign-attachments/{$this->campaign->id}");
    if (is_dir($dir)) {
        foreach (glob($dir . '/*') as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }
});

it('uploads a file successfully', function () {
    $file = UploadedFile::fake()->createWithContent('test.txt', 'Test content');

    $response = $this->actingAs($this->user)
        ->postJson("/api/campaigns/{$this->campaign->id}/attachments", [
            'file' => $file,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('original_filename', 'test.txt');
    $response->assertJsonPath('extraction_status', 'pending');

    expect(CampaignAttachment::where('campaign_id', $this->campaign->id)->count())->toBe(1);
});

it('rejects upload over 25MB', function () {
    $file = UploadedFile::fake()->create('huge.pdf', 26 * 1024);  // 26 MB in KB

    $response = $this->actingAs($this->user)
        ->postJson("/api/campaigns/{$this->campaign->id}/attachments", [
            'file' => $file,
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['file']);
});

it('rejects upload when 5 attachments already exist', function () {
    for ($i = 0; $i < 5; $i++) {
        CampaignAttachment::create([
            'campaign_id'       => $this->campaign->id,
            'original_filename' => "file{$i}.txt",
            'stored_filename'   => "f{$i}.txt",
            'mime_type'         => 'text/plain',
            'size_bytes'        => 100,
            'extraction_status' => 'completed',
        ]);
    }

    $file = UploadedFile::fake()->createWithContent('sixth.txt', 'too many');

    $response = $this->actingAs($this->user)
        ->postJson("/api/campaigns/{$this->campaign->id}/attachments", [
            'file' => $file,
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['file']);
});

it('lists attachments for a campaign', function () {
    foreach (['a', 'b', 'c'] as $name) {
        CampaignAttachment::create([
            'campaign_id'       => $this->campaign->id,
            'original_filename' => "{$name}.txt",
            'stored_filename'   => "{$name}.txt",
            'mime_type'         => 'text/plain',
            'size_bytes'        => 100,
            'extraction_status' => 'completed',
        ]);
    }

    $response = $this->actingAs($this->user)
        ->getJson("/api/campaigns/{$this->campaign->id}/attachments");

    $response->assertOk();
    $response->assertJsonCount(3, 'data');
});

it('deletes an attachment', function () {
    $attachment = CampaignAttachment::create([
        'campaign_id'       => $this->campaign->id,
        'original_filename' => 'gone.txt',
        'stored_filename'   => 'gone.txt',
        'mime_type'         => 'text/plain',
        'size_bytes'        => 100,
        'extraction_status' => 'completed',
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson("/api/campaigns/{$this->campaign->id}/attachments/{$attachment->id}");

    $response->assertOk();
    expect(CampaignAttachment::find($attachment->id))->toBeNull();
});

it('dispatches ExtractAttachmentTextJob on successful upload', function () {
    $file = UploadedFile::fake()->createWithContent('extract.txt', 'Content');

    $response = $this->actingAs($this->user)
        ->postJson("/api/campaigns/{$this->campaign->id}/attachments", [
            'file' => $file,
        ]);

    $response->assertCreated();
    Queue::assertPushed(\App\Domain\Campaign\Jobs\ExtractAttachmentTextJob::class);
});
