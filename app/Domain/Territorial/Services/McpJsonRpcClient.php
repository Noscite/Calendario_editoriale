<?php

declare(strict_types=1);

namespace App\Domain\Territorial\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class McpJsonRpcClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $bearerToken = null,
        private readonly int $timeoutSeconds = 30,
    ) {}

    /**
     * Chiama un tool MCP via JSON-RPC 2.0.
     * Il server può rispondere con result.content[0].text contenente JSON-as-string
     * oppure direttamente result come oggetto.
     */
    public function callTool(string $toolName, array $arguments = []): array
    {
        $payload = [
            'jsonrpc' => '2.0',
            'id'      => random_int(1, PHP_INT_MAX),
            'method'  => 'tools/call',
            'params'  => [
                'name'      => $toolName,
                'arguments' => $arguments,
            ],
        ];

        $http = Http::timeout($this->timeoutSeconds)->acceptJson()->asJson();
        if ($this->bearerToken) {
            $http = $http->withToken($this->bearerToken);
        }

        $response = $http->post($this->baseUrl, $payload);

        if (! $response->successful()) {
            throw new RuntimeException(
                "MCP call failed: HTTP {$response->status()} body=" . substr($response->body(), 0, 500)
            );
        }

        $data = $response->json();

        if (isset($data['error'])) {
            $msg = $data['error']['message'] ?? json_encode($data['error']);
            throw new RuntimeException("MCP returned error: {$msg}");
        }

        $result = $data['result'] ?? [];

        // Formato MCP standard: result.content[0].text è JSON serializzato
        if (isset($result['content'][0]['text'])) {
            $text = $result['content'][0]['text'];
            $decoded = json_decode($text, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
            // Se non è JSON, ritorna come raw text
            return ['__raw' => $text];
        }

        return $result;
    }
}
