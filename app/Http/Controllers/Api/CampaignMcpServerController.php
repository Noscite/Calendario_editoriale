<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Campaign\Models\Campaign;
use App\Domain\Mcp\Models\CampaignMcpServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class CampaignMcpServerController extends Controller
{
    public function index(int $campaignId): JsonResponse
    {
        $campaign = Campaign::findOrFail($campaignId);

        $data = $campaign->mcpServers()->withoutGlobalScopes()->orderBy('id')->get()
            ->map(fn (CampaignMcpServer $m) => $this->serialize($m))
            ->all();

        return response()->json(['data' => $data]);
    }

    public function store(int $campaignId, Request $request): JsonResponse
    {
        $campaign = Campaign::findOrFail($campaignId);

        $data = $request->validate([
            'name'               => 'required|string|max:100',
            'url'                => 'required|url|max:500',
            'api_key'            => 'nullable|string|max:500',
            'scopes'             => 'nullable|array',
            'scopes.*'           => 'string|max:64',
            'is_active'          => 'nullable|boolean',
            'override_brand_mcp' => 'nullable|boolean',
        ]);

        $mcp = new CampaignMcpServer([
            'campaign_id'        => $campaign->id,
            'name'               => $data['name'],
            'url'                => $data['url'],
            'scopes'             => $data['scopes'] ?? null,
            'is_active'          => $data['is_active'] ?? true,
            'override_brand_mcp' => $data['override_brand_mcp'] ?? false,
        ]);
        $mcp->setApiKey($data['api_key'] ?? null);
        $mcp->save();

        return response()->json($this->serialize($mcp), 201);
    }

    public function destroy(int $campaignId, int $mcpId): JsonResponse
    {
        $campaign = Campaign::findOrFail($campaignId);

        $mcp = CampaignMcpServer::where('id', $mcpId)
            ->where('campaign_id', $campaign->id)
            ->firstOrFail();

        $mcp->delete();

        return response()->json(['success' => true]);
    }

    private function serialize(CampaignMcpServer $m): array
    {
        return [
            'id'                 => $m->id,
            'name'               => $m->name,
            'url'                => $m->url,
            'has_api_key'        => ! empty($m->encrypted_api_key),
            'scopes'             => $m->scopes,
            'is_active'          => (bool) $m->is_active,
            'override_brand_mcp' => (bool) $m->override_brand_mcp,
            'created_at'         => $m->created_at?->toIso8601String(),
        ];
    }
}
