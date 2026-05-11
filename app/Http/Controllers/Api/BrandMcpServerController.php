<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Brand\Models\Brand;
use App\Domain\Mcp\Models\BrandMcpServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class BrandMcpServerController extends Controller
{
    public function index(int $brandId): JsonResponse
    {
        $brand = Brand::findOrFail($brandId);

        $data = $brand->mcpServers()->withoutGlobalScopes()->orderBy('id')->get()
            ->map(fn (BrandMcpServer $m) => $this->serialize($m))
            ->all();

        return response()->json(['data' => $data]);
    }

    public function store(int $brandId, Request $request): JsonResponse
    {
        $brand = Brand::findOrFail($brandId);

        $data = $request->validate([
            'name'      => 'required|string|max:100',
            'url'       => 'required|url|max:500',
            'api_key'   => 'nullable|string|max:500',
            'scopes'    => 'nullable|array',
            'scopes.*'  => 'string|max:64',
            'is_active' => 'nullable|boolean',
        ]);

        $mcp = new BrandMcpServer([
            'brand_id'  => $brand->id,
            'name'      => $data['name'],
            'url'       => $data['url'],
            'scopes'    => $data['scopes'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
        $mcp->setApiKey($data['api_key'] ?? null);
        $mcp->save();

        return response()->json($this->serialize($mcp), 201);
    }

    public function destroy(int $brandId, int $mcpId): JsonResponse
    {
        $brand = Brand::findOrFail($brandId);

        $mcp = BrandMcpServer::where('id', $mcpId)
            ->where('brand_id', $brand->id)
            ->firstOrFail();

        $mcp->delete();

        return response()->json(['success' => true]);
    }

    private function serialize(BrandMcpServer $m): array
    {
        return [
            'id'         => $m->id,
            'name'       => $m->name,
            'url'        => $m->url,
            'has_api_key' => ! empty($m->encrypted_api_key),
            'scopes'     => $m->scopes,
            'is_active'  => (bool) $m->is_active,
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }
}
