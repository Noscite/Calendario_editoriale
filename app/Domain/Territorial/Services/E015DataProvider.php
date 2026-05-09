<?php

declare(strict_types=1);

namespace App\Domain\Territorial\Services;

use App\Domain\Territorial\Contracts\TerritorialDataProviderInterface;
use App\Domain\Territorial\DTOs\EventPayload;
use Carbon\Carbon;

class E015DataProvider implements TerritorialDataProviderInterface
{
    public function __construct(
        private readonly McpJsonRpcClient $client,
        private readonly string $tenantId = '1',  // string per match inputSchema MCP
    ) {}

    public function source(): string
    {
        return 'e015';
    }

    public function listEventIds(int $limit = 100, int $offset = 0): array
    {
        $result = $this->client->callTool('e015_eventi_x_m_l_default', [
            '_limit'    => $limit,
            '_offset'   => $offset,
            '_slim'     => true,
            'format'    => 'json',
            'tenant_id' => $this->tenantId,
        ]);

        // Il MCP E015 ritorna {"results": [...], "pagination": ...}.
        // Manteniamo retro-compat per shape future ('data', 'items') o array piatto.
        $items = $result['results'] ?? $result['data'] ?? $result['items'] ?? $result;
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($e) => is_array($e) ? ($e['Id'] ?? null) : null,
            $items
        )));
    }

    public function fetchEvent(int|string $externalId): ?EventPayload
    {
        $result = $this->client->callTool('e015_eventi_x_m_l_default', [
            '_id'       => (int) $externalId,
            '_slim'     => false,
            'format'    => 'json',
            'tenant_id' => $this->tenantId,
        ]);

        // Il MCP risponde con un singolo record dentro 'results' (lista da 1) o
        // direttamente come oggetto top-level con 'Id'.
        $event = null;
        if (isset($result['results'][0]['Id'])) {
            $event = $result['results'][0];
        } elseif (isset($result['data']['Id'])) {
            $event = $result['data'];
        } elseif (isset($result['Id'])) {
            $event = $result;
        }

        if (! $event) {
            return null;
        }

        return $this->mapToPayload($event);
    }

    private function mapToPayload(array $raw): EventPayload
    {
        $venue       = $raw['Venues'][0] ?? [];
        $address     = $venue['Address'] ?? [];
        $geolocation = $venue['Geolocation'] ?? [];
        $schedule    = $raw['Schedules'][0] ?? [];

        $description = $schedule['Description']['It']
            ?? $raw['Description']['It']
            ?? null;

        // Il payload E015 usa 'Type' (non 'MediaType' come da spec iniziale).
        // Manteniamo retro-compat su 'MediaType' nel caso di future varianti.
        $imageUrl = null;
        foreach ($raw['MediaResources'] ?? [] as $media) {
            $mediaType = $media['Type'] ?? $media['MediaType'] ?? null;
            if ($mediaType === 'image' && ! empty($media['Url'])) {
                $imageUrl = $media['Url'];
                break;
            }
        }

        return new EventPayload(
            externalId:  (string) $raw['Id'],
            title:       $raw['Name']['It'] ?? '',
            abstract:    $raw['Abstract']['It'] ?? null,
            description: $description,
            categories:  $raw['Categories']['It'] ?? [],
            venueName:   $venue['Name']['It'] ?? null,
            city:        $address['City'] ?? null,
            province:    $address['Province'] ?? null,
            lat:         isset($geolocation['YCoord']) ? (float) $geolocation['YCoord'] : null, // Y = lat
            lng:         isset($geolocation['XCoord']) ? (float) $geolocation['XCoord'] : null, // X = lng
            startAt:     ! empty($schedule['StartDatetime']) ? Carbon::parse($schedule['StartDatetime']) : null,
            endAt:       ! empty($schedule['EndDatetime']) ? Carbon::parse($schedule['EndDatetime']) : null,
            externalUrl: $raw['ExternalUrl'] ?? null,
            imageUrl:    $imageUrl,
            raw:         $raw,
        );
    }
}
