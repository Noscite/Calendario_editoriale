<?php

declare(strict_types=1);

use App\Domain\Territorial\Services\E015DataProvider;
use App\Domain\Territorial\Services\McpJsonRpcClient;

afterEach(function () {
    Mockery::close();
});

it('lists event ids from MCP results envelope', function () {
    $mockClient = Mockery::mock(McpJsonRpcClient::class);
    $mockClient->shouldReceive('callTool')
        ->once()
        ->with('e015_eventi_x_m_l_default', Mockery::on(fn ($args) =>
            $args['_slim'] === true && $args['tenant_id'] === '1'
        ))
        ->andReturn([
            'results' => [
                ['Id' => 83, 'Name' => ['It' => 'Concerto']],
                ['Id' => 84, 'Name' => ['It' => 'Sagra']],
            ],
            'pagination' => ['total' => 2],
        ]);

    $provider = new E015DataProvider($mockClient, '1');
    $ids = $provider->listEventIds(10, 0);

    expect($ids)->toBe([83, 84]);
});

it('fetches and maps a single event detail with all geographic + media fields', function () {
    $mockClient = Mockery::mock(McpJsonRpcClient::class);
    $mockClient->shouldReceive('callTool')
        ->once()
        ->andReturn([
            'results' => [[
                'Id' => 83,
                'Name' => ['It' => 'Concerto di Primavera'],
                'Abstract' => ['It' => 'Concerto tradizionale'],
                'Categories' => ['It' => ['Musica', 'Tradizione']],
                'Schedules' => [[
                    'StartDatetime' => '2026-05-17T17:00:00Z',
                    'EndDatetime'   => '2026-05-17T19:00:00Z',
                    'Description'   => ['It' => 'Concerto del Corpo Musicale Vapriese'],
                ]],
                'Venues' => [[
                    'Name'        => ['It' => 'Chiesa San Nicolò'],
                    'Address'     => ['City' => "Vaprio d'Adda", 'Province' => 'MI'],
                    'Geolocation' => ['XCoord' => 9.55, 'YCoord' => 45.59],
                ]],
                'MediaResources' => [[
                    'Type' => 'image',
                    'Url'  => 'https://s3.example/locandina.jpg',
                ]],
            ]],
        ]);

    $provider = new E015DataProvider($mockClient, '1');
    $event = $provider->fetchEvent(83);

    expect($event)->not->toBeNull();
    expect($event->title)->toBe('Concerto di Primavera');
    expect($event->city)->toBe("Vaprio d'Adda");
    expect($event->province)->toBe('MI');
    expect($event->lat)->toBe(45.59);
    expect($event->lng)->toBe(9.55);
    expect($event->categories)->toBe(['Musica', 'Tradizione']);
    expect($event->imageUrl)->toBe('https://s3.example/locandina.jpg');
    expect($event->startAt->toIso8601String())->toContain('2026-05-17');
});

it('returns null when MCP response has no Id', function () {
    $mockClient = Mockery::mock(McpJsonRpcClient::class);
    $mockClient->shouldReceive('callTool')
        ->once()
        ->andReturn(['results' => []]);

    $provider = new E015DataProvider($mockClient, '1');

    expect($provider->fetchEvent(999))->toBeNull();
});

it('falls back to legacy data/items envelopes for forward-compat', function () {
    $mockClient = Mockery::mock(McpJsonRpcClient::class);
    $mockClient->shouldReceive('callTool')
        ->once()
        ->andReturn(['data' => [['Id' => 100], ['Id' => 101]]]);

    $provider = new E015DataProvider($mockClient, '1');

    expect($provider->listEventIds(10, 0))->toBe([100, 101]);
});
