<?php

declare(strict_types=1);

use App\Domain\Campaign\Models\CampaignAttachment;
use App\Domain\Generation\Services\PromptBuilder;
use Carbon\Carbon;

beforeEach(function () {
    [, $org] = createAuthenticatedUser();
    $this->brand    = createBrand($org, ['sector' => 'food']);
    $this->campaign = createCampaign($org, ['name' => 'Lancio prodotto Q3']);

    $this->builder = app(PromptBuilder::class);
});

function buildStrategyPromptWithCampaign(PromptBuilder $builder, $brand, $campaign): string
{
    return $builder->buildStrategyPrompt(
        brandName:      $brand->name,
        brandInfo:      [
            'sector'         => $brand->sector,
            'description'    => 'Test',
            'tone_of_voice'  => 'amichevole',
            'brand_values'   => [],
            'voice_examples' => [],
        ],
        projectInfo:    ['brief' => 'Test', 'objectives' => []],
        startDate:      Carbon::parse('2026-06-01'),
        endDate:        Carbon::parse('2026-06-30'),
        platforms:      ['instagram'],
        postsPerWeek:   ['instagram' => 2],
        themes:         ['Educational'],
        urlContext:     null,
        ragContext:     '',
        buyerPersonas:  [],
        contentMixData: [],
        brand:          $brand,
        campaign:       $campaign,
    );
}

it('injects campaign knowledge base when campaign has completed attachments', function () {
    CampaignAttachment::create([
        'campaign_id'       => $this->campaign->id,
        'original_filename' => 'product-spec.pdf',
        'stored_filename'   => 'product-spec.pdf',
        'mime_type'         => 'application/pdf',
        'size_bytes'        => 1000,
        'extraction_status' => 'completed',
        'extracted_text'    => 'Il prodotto X ha una shelf life di 12 mesi a temperatura ambiente.',
        'extracted_at'      => now(),
    ]);

    $prompt = buildStrategyPromptWithCampaign($this->builder, $this->brand, $this->campaign);

    expect($prompt)->toContain('KNOWLEDGE BASE — Documenti allegati alla campagna');
    expect($prompt)->toContain('product-spec.pdf');
    expect($prompt)->toContain('shelf life di 12 mesi');
});

it('does NOT inject KB section when campaign has no completed attachments', function () {
    // Solo pending/failed attachments — non devono apparire nel prompt
    CampaignAttachment::create([
        'campaign_id'       => $this->campaign->id,
        'original_filename' => 'wip.pdf',
        'stored_filename'   => 'wip.pdf',
        'mime_type'         => 'application/pdf',
        'size_bytes'        => 1000,
        'extraction_status' => 'pending',
    ]);
    CampaignAttachment::create([
        'campaign_id'       => $this->campaign->id,
        'original_filename' => 'broken.pdf',
        'stored_filename'   => 'broken.pdf',
        'mime_type'         => 'application/pdf',
        'size_bytes'        => 1000,
        'extraction_status' => 'failed',
        'extraction_error'  => 'Parser error',
    ]);

    $prompt = buildStrategyPromptWithCampaign($this->builder, $this->brand, $this->campaign);

    expect($prompt)->not->toContain('KNOWLEDGE BASE — Documenti allegati alla campagna');
    expect($prompt)->not->toContain('wip.pdf');
});

it('does NOT inject KB section when campaign parameter is null (no project↔campaign link)', function () {
    $prompt = $this->builder->buildStrategyPrompt(
        brandName:      $this->brand->name,
        brandInfo:      [
            'sector'         => $this->brand->sector,
            'description'    => 'Test',
            'tone_of_voice'  => 'amichevole',
            'brand_values'   => [],
            'voice_examples' => [],
        ],
        projectInfo:    ['brief' => 'Test', 'objectives' => []],
        startDate:      Carbon::parse('2026-06-01'),
        endDate:        Carbon::parse('2026-06-30'),
        platforms:      ['instagram'],
        postsPerWeek:   ['instagram' => 2],
        themes:         ['Educational'],
        urlContext:     null,
        ragContext:     '',
        buyerPersonas:  [],
        contentMixData: [],
        brand:          $this->brand,
        // campaign: NOT passed
    );

    expect($prompt)->not->toContain('KNOWLEDGE BASE — Documenti allegati alla campagna');
});

it('handles multiple completed attachments', function () {
    foreach ([
        ['name' => 'doc1.txt',  'text' => 'Fatto specifico A.'],
        ['name' => 'doc2.txt',  'text' => 'Fatto specifico B.'],
    ] as $row) {
        CampaignAttachment::create([
            'campaign_id'       => $this->campaign->id,
            'original_filename' => $row['name'],
            'stored_filename'   => $row['name'],
            'mime_type'         => 'text/plain',
            'size_bytes'        => strlen($row['text']),
            'extraction_status' => 'completed',
            'extracted_text'    => $row['text'],
            'extracted_at'      => now(),
        ]);
    }

    $prompt = buildStrategyPromptWithCampaign($this->builder, $this->brand, $this->campaign);

    expect($prompt)->toContain('doc1.txt');
    expect($prompt)->toContain('doc2.txt');
    expect($prompt)->toContain('Fatto specifico A');
    expect($prompt)->toContain('Fatto specifico B');
});
