<?php

declare(strict_types=1);

use App\Domain\Territorial\Contracts\TerritorialDataProviderInterface;
use App\Domain\Territorial\DTOs\EventPayload;
use App\Domain\Territorial\Jobs\SyncTerritorialEventsJob;
use App\Domain\Territorial\Models\TerritorialEvent;
use App\Domain\Territorial\Services\EventImageDownloader;
use Carbon\Carbon;

beforeEach(function () {
    // Configura il job con un provider fake invece del binding di config
    config(['services.territorial.providers' => [FakeTerritorialProvider::class]]);
    FakeTerritorialProvider::reset();
});

it('upserts events on first sync (create branch)', function () {
    FakeTerritorialProvider::setEvents([
        makePayload('e1', 'Sagra del Vino'),
        makePayload('e2', 'Festa di Paese'),
    ]);

    $downloader = Mockery::mock(EventImageDownloader::class);
    $downloader->shouldReceive('download')->andReturn(true);

    (new SyncTerritorialEventsJob)->handle($downloader);

    expect(TerritorialEvent::count())->toBe(2);
    expect(TerritorialEvent::where('source', 'fake')->where('external_id', 'e1')->exists())->toBeTrue();
    expect(TerritorialEvent::where('external_id', 'e1')->first()->title)->toBe('Sagra del Vino');
});

it('updates existing events on second sync (update branch) and preserves first_seen_at', function () {
    // Pre-create
    $original = TerritorialEvent::create([
        'source'        => 'fake',
        'external_id'   => 'e1',
        'title'         => 'Old Title',
        'status'        => 'active',
        'first_seen_at' => Carbon::parse('2026-01-01'),
        'last_seen_at'  => Carbon::parse('2026-01-01'),
    ]);

    FakeTerritorialProvider::setEvents([
        makePayload('e1', 'New Title'),
    ]);

    $downloader = Mockery::mock(EventImageDownloader::class);
    $downloader->shouldReceive('download')->andReturn(true);

    (new SyncTerritorialEventsJob)->handle($downloader);

    $original->refresh();
    expect($original->title)->toBe('New Title');
    expect($original->first_seen_at->toDateString())->toBe('2026-01-01');
    expect($original->last_seen_at->isToday())->toBeTrue();
});

it('marks cancelled events that disappear from the feed', function () {
    // Pre-create due eventi attivi
    TerritorialEvent::create([
        'source' => 'fake', 'external_id' => 'e1', 'title' => 'Stays',
        'status' => 'active', 'first_seen_at' => now(), 'last_seen_at' => now(),
    ]);
    TerritorialEvent::create([
        'source' => 'fake', 'external_id' => 'e2', 'title' => 'Disappears',
        'status' => 'active', 'first_seen_at' => now(), 'last_seen_at' => now(),
    ]);

    // Il provider ora ritorna solo e1
    FakeTerritorialProvider::setEvents([
        makePayload('e1', 'Stays Updated'),
    ]);

    $downloader = Mockery::mock(EventImageDownloader::class);
    $downloader->shouldReceive('download')->andReturn(true);

    (new SyncTerritorialEventsJob)->handle($downloader);

    expect(TerritorialEvent::where('external_id', 'e1')->first()->status)->toBe('active');
    expect(TerritorialEvent::where('external_id', 'e2')->first()->status)->toBe('cancelled');
});

it('triggers image download only when image_url is present', function () {
    FakeTerritorialProvider::setEvents([
        makePayload('with-img', 'Has image', imageUrl: 'https://s3/x.jpg'),
        makePayload('no-img', 'No image', imageUrl: null),
    ]);

    $downloader = Mockery::mock(EventImageDownloader::class);
    $downloader->shouldReceive('download')->once()->andReturn(true);

    (new SyncTerritorialEventsJob)->handle($downloader);

    expect(TerritorialEvent::count())->toBe(2);
});

// ── Helpers ──────────────────────────────────────────────────────

function makePayload(string $externalId, string $title, ?string $imageUrl = null): EventPayload {
    return new EventPayload(
        externalId:  $externalId,
        title:       $title,
        abstract:    null,
        description: null,
        categories:  [],
        venueName:   null,
        city:        'Milano',
        province:    'MI',
        lat:         null,
        lng:         null,
        startAt:     Carbon::parse('2026-06-01 10:00:00'),
        endAt:       Carbon::parse('2026-06-01 12:00:00'),
        externalUrl: null,
        imageUrl:    $imageUrl,
        raw:         ['Id' => $externalId, 'Name' => ['It' => $title]],
    );
}

class FakeTerritorialProvider implements TerritorialDataProviderInterface
{
    /** @var array<int, EventPayload> */
    private static array $events = [];

    public static function setEvents(array $events): void
    {
        self::$events = $events;
    }

    public static function reset(): void
    {
        self::$events = [];
    }

    public function source(): string
    {
        return 'fake';
    }

    public function listEventIds(int $limit = 100, int $offset = 0): array
    {
        if ($offset > 0) {
            return [];
        }
        return array_map(fn (EventPayload $e) => $e->externalId, self::$events);
    }

    public function fetchEvent(int|string $externalId): ?EventPayload
    {
        foreach (self::$events as $e) {
            if ((string) $e->externalId === (string) $externalId) {
                return $e;
            }
        }
        return null;
    }
}
