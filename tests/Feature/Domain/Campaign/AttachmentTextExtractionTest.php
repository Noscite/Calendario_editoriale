<?php

declare(strict_types=1);

use App\Domain\Campaign\Jobs\ExtractAttachmentTextJob;
use App\Domain\Campaign\Models\CampaignAttachment;
use App\Domain\Campaign\Services\AttachmentTextExtractor;

beforeEach(function () {
    [$user, $org] = createAuthenticatedUser();
    $this->campaign = createCampaign($org);
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

function makeFixtureFile(int $campaignId, string $filename, string $content): string
{
    $dir = storage_path("app/campaign-attachments/{$campaignId}");
    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $path = "{$dir}/{$filename}";
    file_put_contents($path, $content);

    return $path;
}

it('extracts text from plain .txt file', function () {
    $path = makeFixtureFile($this->campaign->id, 'test.txt', "Questo è un file di test.\nSeconda riga.");

    $attachment = CampaignAttachment::create([
        'campaign_id'       => $this->campaign->id,
        'original_filename' => 'test.txt',
        'stored_filename'   => 'test.txt',
        'mime_type'         => 'text/plain',
        'size_bytes'        => filesize($path),
    ]);

    app(AttachmentTextExtractor::class)->extract($attachment);

    $attachment->refresh();
    expect($attachment->extraction_status)->toBe('completed');
    expect($attachment->extracted_text)->toContain('Questo è un file di test');
    expect($attachment->extracted_at)->not->toBeNull();
});

it('extracts text from CSV', function () {
    makeFixtureFile($this->campaign->id, 'data.csv', "name,value\nfoo,1\nbar,2\n");

    $attachment = CampaignAttachment::create([
        'campaign_id'       => $this->campaign->id,
        'original_filename' => 'data.csv',
        'stored_filename'   => 'data.csv',
        'mime_type'         => 'text/csv',
        'size_bytes'        => 22,
    ]);

    app(AttachmentTextExtractor::class)->extract($attachment);

    $attachment->refresh();
    expect($attachment->extraction_status)->toBe('completed');
    expect($attachment->extracted_text)->toContain('foo,1');
});

it('extracts text from HTML and strips tags', function () {
    makeFixtureFile($this->campaign->id, 'page.html', '<html><body><h1>Titolo</h1><p>Paragrafo importante.</p></body></html>');

    $attachment = CampaignAttachment::create([
        'campaign_id'       => $this->campaign->id,
        'original_filename' => 'page.html',
        'stored_filename'   => 'page.html',
        'mime_type'         => 'text/html',
        'size_bytes'        => 200,
    ]);

    app(AttachmentTextExtractor::class)->extract($attachment);

    $attachment->refresh();
    expect($attachment->extraction_status)->toBe('completed');
    expect($attachment->extracted_text)->toContain('Titolo');
    expect($attachment->extracted_text)->toContain('Paragrafo importante');
    expect($attachment->extracted_text)->not->toContain('<h1>');
});

it('marks unsupported mime as unsupported (not failed)', function () {
    $attachment = CampaignAttachment::create([
        'campaign_id'       => $this->campaign->id,
        'original_filename' => 'video.mp4',
        'stored_filename'   => 'video.mp4',
        'mime_type'         => 'video/mp4',
        'size_bytes'        => 1024,
    ]);

    // Crea anche il file fisico (verifichiamo unsupported anche se il file esiste)
    makeFixtureFile($this->campaign->id, 'video.mp4', 'fake binary content');

    app(AttachmentTextExtractor::class)->extract($attachment);

    $attachment->refresh();
    expect($attachment->extraction_status)->toBe('unsupported');
    expect($attachment->extracted_text)->toBeNull();
    expect($attachment->extraction_error)->toContain('video/mp4');
});

it('marks failed when file is missing on disk', function () {
    $attachment = CampaignAttachment::create([
        'campaign_id'       => $this->campaign->id,
        'original_filename' => 'ghost.txt',
        'stored_filename'   => 'ghost.txt',
        'mime_type'         => 'text/plain',
        'size_bytes'        => 100,
    ]);

    app(AttachmentTextExtractor::class)->extract($attachment);

    $attachment->refresh();
    expect($attachment->extraction_status)->toBe('failed');
    expect($attachment->extraction_error)->toContain('non trovato');
});

it('truncates extracted text at 200k chars', function () {
    makeFixtureFile($this->campaign->id, 'big.txt', str_repeat('a', 250_000));

    $attachment = CampaignAttachment::create([
        'campaign_id'       => $this->campaign->id,
        'original_filename' => 'big.txt',
        'stored_filename'   => 'big.txt',
        'mime_type'         => 'text/plain',
        'size_bytes'        => 250_000,
    ]);

    app(AttachmentTextExtractor::class)->extract($attachment);

    $attachment->refresh();
    expect(mb_strlen($attachment->extracted_text))->toBeLessThanOrEqual(200_100);
    expect($attachment->extracted_text)->toContain('troncato');
});

it('job dispatches extractor for valid attachment', function () {
    makeFixtureFile($this->campaign->id, 'job.txt', 'Job test');

    $attachment = CampaignAttachment::create([
        'campaign_id'       => $this->campaign->id,
        'original_filename' => 'job.txt',
        'stored_filename'   => 'job.txt',
        'mime_type'         => 'text/plain',
        'size_bytes'        => 8,
    ]);

    (new ExtractAttachmentTextJob($attachment->id))->handle(app(AttachmentTextExtractor::class));

    $attachment->refresh();
    expect($attachment->extraction_status)->toBe('completed');
    expect($attachment->extracted_text)->toBe('Job test');
});

it('attachmentsReadyForAI scope returns only completed with text', function () {
    $completed = CampaignAttachment::create([
        'campaign_id'       => $this->campaign->id,
        'original_filename' => 'ok.txt',
        'stored_filename'   => 'ok.txt',
        'mime_type'         => 'text/plain',
        'size_bytes'        => 10,
        'extraction_status' => 'completed',
        'extracted_text'    => 'Hello world',
    ]);
    $pending = CampaignAttachment::create([
        'campaign_id'       => $this->campaign->id,
        'original_filename' => 'pending.txt',
        'stored_filename'   => 'pending.txt',
        'mime_type'         => 'text/plain',
        'size_bytes'        => 10,
        'extraction_status' => 'pending',
    ]);
    $unsupported = CampaignAttachment::create([
        'campaign_id'       => $this->campaign->id,
        'original_filename' => 'unsupp.mp4',
        'stored_filename'   => 'unsupp.mp4',
        'mime_type'         => 'video/mp4',
        'size_bytes'        => 10,
        'extraction_status' => 'unsupported',
    ]);

    $ready = $this->campaign->attachmentsReadyForAI()->get();

    expect($ready->pluck('id')->toArray())->toBe([$completed->id]);
});
