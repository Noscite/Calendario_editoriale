<?php

declare(strict_types=1);

use App\Domain\Mcp\Models\BrandMcpServer;
use App\Domain\Mcp\Models\CampaignMcpServer;

beforeEach(function () {
    [, $org] = createAuthenticatedUser();
    $this->brand    = createBrand($org);
    $this->campaign = createCampaign($org);
    // Aggancia campaign ↔ brand (belongsToMany via brand_campaign pivot)
    $this->campaign->brands()->attach($this->brand->id);
    $this->campaign->refresh()->load('brands');
});

it('stores and retrieves encrypted API key via Crypt round-trip', function () {
    $mcp = new BrandMcpServer([
        'brand_id'  => $this->brand->id,
        'name'      => 'Test',
        'url'       => 'https://example.com/sse',
        'is_active' => true,
    ]);
    $mcp->setApiKey('secret-token-123');
    $mcp->save();

    $reloaded = BrandMcpServer::find($mcp->id);
    expect($reloaded->getApiKey())->toBe('secret-token-123');
    expect($reloaded->encrypted_api_key)->not->toBe('secret-token-123'); // is encrypted
});

it('effectiveMcpServers returns union of campaign + brand MCP by default', function () {
    $brandMcp = new BrandMcpServer([
        'brand_id'  => $this->brand->id,
        'name'      => 'Brand MCP',
        'url'       => 'https://mcp-brand.example.com/sse',
        'is_active' => true,
    ]);
    $brandMcp->setApiKey('brand-key');
    $brandMcp->save();

    $campMcp = new CampaignMcpServer([
        'campaign_id'        => $this->campaign->id,
        'name'               => 'Campaign MCP',
        'url'                => 'https://mcp-camp.example.com/sse',
        'is_active'          => true,
        'override_brand_mcp' => false,
    ]);
    $campMcp->setApiKey('camp-key');
    $campMcp->save();

    $this->campaign->refresh()->load(['mcpServers', 'brands.mcpServers']);
    $effective = $this->campaign->effectiveMcpServers();

    expect(count($effective))->toBe(2);
    $names = array_column($effective, 'name');
    expect($names)->toContain('Brand MCP');
    expect($names)->toContain('Campaign MCP');
});

it('effectiveMcpServers excludes brand MCP when override_brand_mcp=true', function () {
    BrandMcpServer::create([
        'brand_id'  => $this->brand->id,
        'name'      => 'Brand MCP',
        'url'       => 'https://mcp-brand.example.com/sse',
        'is_active' => true,
    ]);

    CampaignMcpServer::create([
        'campaign_id'        => $this->campaign->id,
        'name'               => 'Campaign Only',
        'url'                => 'https://mcp-only.example.com/sse',
        'is_active'          => true,
        'override_brand_mcp' => true,
    ]);

    $this->campaign->refresh()->load(['mcpServers', 'brands.mcpServers']);
    $effective = $this->campaign->effectiveMcpServers();

    expect(count($effective))->toBe(1);
    expect($effective[0]['name'])->toBe('Campaign Only');
});

it('effectiveMcpServers excludes inactive MCPs', function () {
    BrandMcpServer::create([
        'brand_id'  => $this->brand->id,
        'name'      => 'Inactive Brand',
        'url'       => 'https://mcp-inactive.example.com/sse',
        'is_active' => false,
    ]);
    BrandMcpServer::create([
        'brand_id'  => $this->brand->id,
        'name'      => 'Active Brand',
        'url'       => 'https://mcp-active.example.com/sse',
        'is_active' => true,
    ]);

    $this->campaign->refresh()->load(['mcpServers', 'brands.mcpServers']);
    $effective = $this->campaign->effectiveMcpServers();

    expect(count($effective))->toBe(1);
    expect($effective[0]['name'])->toBe('Active Brand');
});

it('effectiveMcpServers returns empty when no MCP configured', function () {
    $this->campaign->refresh()->load(['mcpServers', 'brands.mcpServers']);
    $effective = $this->campaign->effectiveMcpServers();

    expect($effective)->toBe([]);
});

it('effectiveMcpServers with brandId scope returns only that brand MCP', function () {
    $brand2 = createBrand($this->brand->organization);
    $this->campaign->brands()->attach($brand2->id);

    BrandMcpServer::create([
        'brand_id'  => $this->brand->id,
        'name'      => 'Brand1 MCP',
        'url'       => 'https://mcp-b1.example.com/sse',
        'is_active' => true,
    ]);
    BrandMcpServer::create([
        'brand_id'  => $brand2->id,
        'name'      => 'Brand2 MCP',
        'url'       => 'https://mcp-b2.example.com/sse',
        'is_active' => true,
    ]);

    $this->campaign->refresh()->load(['mcpServers', 'brands.mcpServers']);
    $effective = $this->campaign->effectiveMcpServers($this->brand->id);

    expect(count($effective))->toBe(1);
    expect($effective[0]['name'])->toBe('Brand1 MCP');
});
