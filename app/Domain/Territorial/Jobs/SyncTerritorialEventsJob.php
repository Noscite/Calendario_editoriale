<?php

declare(strict_types=1);

namespace App\Domain\Territorial\Jobs;

use App\Domain\Territorial\Contracts\TerritorialDataProviderInterface;
use App\Domain\Territorial\Models\TerritorialEvent;
use App\Domain\Territorial\Services\EventImageDownloader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncTerritorialEventsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 1;

    public function handle(EventImageDownloader $downloader): void
    {
        $providerClasses = config('services.territorial.providers', []);

        foreach ($providerClasses as $providerClass) {
            try {
                $provider = app($providerClass);
                $this->syncProvider($provider, $downloader);
            } catch (\Throwable $e) {
                Log::error("[TERRITORIAL] Sync failed for provider {$providerClass}: {$e->getMessage()}");
            }
        }
    }

    private function syncProvider(
        TerritorialDataProviderInterface $provider,
        EventImageDownloader $downloader
    ): void {
        $source = $provider->source();
        $seenExternalIds = [];
        $offset = 0;
        $limit = 100;
        $totalFetched = 0;

        Log::info("[TERRITORIAL] Sync start source={$source}");

        do {
            $ids = $provider->listEventIds($limit, $offset);
            if (empty($ids)) {
                break;
            }

            foreach ($ids as $externalId) {
                $payload = $provider->fetchEvent($externalId);
                if (! $payload) {
                    continue;
                }

                // first_seen_at preservato al create, last_seen_at aggiornato sempre.
                $event = TerritorialEvent::firstOrNew(
                    ['source' => $source, 'external_id' => $payload->externalId]
                );
                $event->fill([
                    'title'              => $payload->title,
                    'abstract'           => $payload->abstract,
                    'description'        => $payload->description,
                    'categories'         => $payload->categories,
                    'venue_name'         => $payload->venueName,
                    'city'               => $payload->city,
                    'province'           => $payload->province,
                    'lat'                => $payload->lat,
                    'lng'                => $payload->lng,
                    'start_at'           => $payload->startAt,
                    'end_at'             => $payload->endAt,
                    'external_url'       => $payload->externalUrl,
                    'image_url_external' => $payload->imageUrl,
                    'raw_payload'        => $payload->raw,
                    'status'             => 'active',
                    'last_seen_at'       => now(),
                ]);
                if (! $event->exists) {
                    $event->first_seen_at = now();
                }
                $event->save();

                $seenExternalIds[] = $payload->externalId;
                $totalFetched++;

                // Scarica immagine se URL nuovo o image_path mancante
                if ($payload->imageUrl && (! $event->image_path || $event->getOriginal('image_url_external') !== $payload->imageUrl)) {
                    $downloader->download($event);
                }
            }

            $offset += $limit;
        } while (count($ids) === $limit);

        // Mark cancelled gli eventi non più nel feed
        $cancelled = TerritorialEvent::where('source', $source)
            ->whereNotIn('external_id', $seenExternalIds)
            ->where('status', 'active')
            ->update(['status' => 'cancelled']);

        Log::info("[TERRITORIAL] Sync done source={$source} fetched={$totalFetched} cancelled={$cancelled}");
    }
}
