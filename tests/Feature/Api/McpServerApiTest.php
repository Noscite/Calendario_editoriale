<?php

declare(strict_types=1);

use App\Domain\Mcp\Models\BrandMcpServer;
use App\Domain\Mcp\Models\CampaignMcpServer;

beforeEach(function () {
    [$this->user, $this->org] = createAuthenticatedUser();
    $this->brand              = createBrand($this->org);
    $this->campaign           = createCampaign($this->org);
});

it('POST /api/brands/{id}/mcp-servers creates a brand MCP', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/brands/{$this->brand->id}/mcp-servers", [
            'name'    => 'Catalogo prodotti',
            'url'     => 'https://mcp.example.com/sse',
            'api_key' => 'super-secret-token',
            'scopes'  => ['read'],
        ]);

    $response->assertCreated();
    $response->assertJsonPath('name', 'Catalogo prodotti');
    $response->assertJsonPath('has_api_key', true);
    $response->assertJsonPath('is_active', true);

    $mcp = BrandMcpServer::where('brand_id', $this->brand->id)->first();
    expect($mcp)->not->toBeNull();
    expect($mcp->getApiKey())->toBe('super-secret-token');
});

it('GET /api/brands/{id}/mcp-servers lists brand MCP servers without leaking API key', function () {
    $mcp = new BrandMcpServer([
        'brand_id'  => $this->brand->id,
        'name'      => 'A',
        'url'       => 'https://a.example.com/sse',
        'is_active' => true,
    ]);
    $mcp->setApiKey('hidden');
    $mcp->save();

    $response = $this->actingAs($this->user)
        ->getJson("/api/brands/{$this->brand->id}/mcp-servers");

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.has_api_key', true);
    // L'API key plaintext NON deve essere mai esposta nelle response
    $body = $response->getContent();
    expect($body)->not->toContain('hidden');
});

it('DELETE /api/brands/{id}/mcp-servers/{mcpId} removes a brand MCP', function () {
    $mcp = BrandMcpServer::create([
        'brand_id'  => $this->brand->id,
        'name'      => 'Bye',
        'url'       => 'https://x.example.com/sse',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson("/api/brands/{$this->brand->id}/mcp-servers/{$mcp->id}");

    $response->assertOk();
    expect(BrandMcpServer::find($mcp->id))->toBeNull();
});

it('POST /api/campaigns/{id}/mcp-servers creates a campaign MCP with override flag', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/campaigns/{$this->campaign->id}/mcp-servers", [
            'name'               => 'CRM cliente',
            'url'                => 'https://crm.example.com/sse',
            'api_key'            => 'crm-key',
            'override_brand_mcp' => true,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('override_brand_mcp', true);

    $mcp = CampaignMcpServer::where('campaign_id', $this->campaign->id)->first();
    expect($mcp->override_brand_mcp)->toBeTrue();
});

it('GET /api/campaigns/{id}/mcp-servers lists campaign MCP servers', function () {
    CampaignMcpServer::create([
        'campaign_id'        => $this->campaign->id,
        'name'               => 'A',
        'url'                => 'https://a.example.com/sse',
        'is_active'          => true,
        'override_brand_mcp' => false,
    ]);
    CampaignMcpServer::create([
        'campaign_id'        => $this->campaign->id,
        'name'               => 'B',
        'url'                => 'https://b.example.com/sse',
        'is_active'          => true,
        'override_brand_mcp' => false,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/campaigns/{$this->campaign->id}/mcp-servers");

    $response->assertOk();
    $response->assertJsonCount(2, 'data');
});

it('rejects invalid url', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/brands/{$this->brand->id}/mcp-servers", [
            'name' => 'Invalid',
            'url'  => 'not-a-url',
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['url']);
});
