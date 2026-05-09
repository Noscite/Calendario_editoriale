<?php

declare(strict_types=1);

namespace App\Domain\Territorial\Services;

use App\Domain\Territorial\Models\TerritorialEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EventImageDownloader
{
    /**
     * Scarica la locandina S3 presigned e la salva su storage locale.
     * Aggiorna territorial_events.image_path.
     */
    public function download(TerritorialEvent $event): bool
    {
        if (! $event->image_url_external) {
            return false;
        }

        try {
            $response = Http::timeout(30)->get($event->image_url_external);
            if (! $response->successful()) {
                Log::warning("[TERRITORIAL] Image download failed for event {$event->id}: HTTP {$response->status()}");
                return false;
            }

            // Estrai estensione dal Content-Type o dall'URL
            $ext = match ($response->header('Content-Type')) {
                'image/jpeg', 'image/jpg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'jpg',
            };

            $relativePath = "territorial/{$event->source}/{$event->external_id}.{$ext}";
            Storage::disk('public')->put($relativePath, $response->body());

            $event->update(['image_path' => $relativePath]);
            return true;

        } catch (\Throwable $e) {
            Log::error("[TERRITORIAL] Image download exception for event {$event->id}: {$e->getMessage()}");
            return false;
        }
    }
}
