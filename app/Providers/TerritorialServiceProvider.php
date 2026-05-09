<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Territorial\Services\E015DataProvider;
use App\Domain\Territorial\Services\McpJsonRpcClient;
use Illuminate\Support\ServiceProvider;

class TerritorialServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(McpJsonRpcClient::class, function () {
            return new McpJsonRpcClient(
                baseUrl: (string) config('services.territorial.e015.mcp_url'),
                bearerToken: config('services.territorial.e015.mcp_token') ?: null,
            );
        });

        $this->app->bind(E015DataProvider::class, function ($app) {
            return new E015DataProvider(
                client: $app->make(McpJsonRpcClient::class),
                tenantId: (string) config('services.territorial.e015.tenant_id', '1'),
            );
        });
    }
}
