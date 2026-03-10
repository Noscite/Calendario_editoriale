<?php

declare(strict_types=1);

namespace App\Domain\Social\Jobs;

use App\Domain\Post\Enums\PublicationStatus;
use App\Domain\Post\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Job schedulato che controlla e pubblica i post programmati.
 *
 * Replica esatta di scheduler_service.py → check_and_publish_posts()
 * Viene eseguito ogni minuto via Laravel Scheduler.
 *
 * Logica:
 * 1. Trova post con scheduled_date = oggi E scheduled_time (HH:MM) = ora corrente
 * 2. Filtra per publication_status IN (scheduled, pending)
 * 3. Per ogni post trovato, dispatcha un PublishPostJob
 *
 * Registrare in routes/console.php:
 *   Schedule::job(new PublishScheduledPostsJob)->everyMinute();
 */
class PublishScheduledPostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    public function handle(): void
    {
        $currentDate = now()->toDateString();
        $currentTime = now()->format('H:i');

        Log::info("Scheduler check: {$currentDate} {$currentTime}");

        // Trova post da pubblicare (replica esatta della query Python)
        // Confronta solo HH:MM (ignora secondi se presenti nel campo scheduled_time)
        $posts = Post::where('scheduled_date', $currentDate)
            ->where(DB::raw("SUBSTRING(scheduled_time, 1, 5)"), $currentTime)
            ->whereIn('publication_status', [
                PublicationStatus::Scheduled,
                'scheduled',
                'pending',
            ])
            ->get();

        Log::info("Found {$posts->count()} posts to publish");

        foreach ($posts as $post) {
            // Dispatcha un job separato per ogni post (isolamento errori)
            PublishPostJob::dispatch($post->id);
        }
    }
}
