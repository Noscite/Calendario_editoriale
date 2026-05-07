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
 * 1. Trova post con scheduled_date = oggi (Europe/Rome) E scheduled_time (HH:MM)
 *    in finestra ±2 min, sempre in Europe/Rome.
 *    Il timezone applicativo (config/app.php) resta UTC per non sfasare i
 *    timestamp Eloquent storici. Lo scheduler converte esplicitamente a
 *    Europe/Rome perché scheduled_time è salvato come ora locale italiana
 *    (convenzione UI/UX: l'utente inserisce "12:30" pensando ora italiana).
 * 2. Filtra per publication_status IN (scheduled)
 * 3. Per ogni post trovato:
 *    - Applica lock ottimistico impostando publication_status = 'publishing'
 *      (evita doppia pubblicazione con più worker paralleli)
 *    - Dispatcha un PublishPostJob
 *
 * Registrare in routes/console.php:
 *   Schedule::job(new PublishScheduledPostsJob)->everyMinute();
 */
class PublishScheduledPostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    public function __construct()
    {
        $this->onQueue('pubblicazione');
    }

    public function handle(): void
    {
        // Calcoliamo la finestra in Europe/Rome perché scheduled_time è salvato
        // come ora locale italiana (convenzione UI/UX). Il timezone applicativo
        // resta UTC per compatibilità con timestamp Eloquent (created_at ecc.).
        $nowRome      = now()->setTimezone('Europe/Rome');
        $currentDate  = $nowRome->toDateString();
        $windowStart  = $nowRome->copy()->subMinutes(2)->format('H:i');
        $windowEnd    = $nowRome->copy()->addMinutes(2)->format('H:i');

        Log::info("Scheduler check: {$currentDate} (Europe/Rome) finestra [{$windowStart} - {$windowEnd}]");

        // Trova post da pubblicare con finestra temporale ±2 minuti.
        // Usa SUBSTRING per confrontare solo HH:MM (ignora secondi nel campo scheduled_time).
        $posts = Post::where('scheduled_date', $currentDate)
            ->whereBetween(
                DB::raw("SUBSTRING(scheduled_time, 1, 5)"),
                [$windowStart, $windowEnd]
            )
            ->where('publication_status', PublicationStatus::Scheduled)
            ->get();

        Log::info("Scheduler: trovati {$posts->count()} post da pubblicare");

        foreach ($posts as $post) {
            // Lock ottimistico: aggiorna a 'publishing' solo se ancora 'scheduled'.
            // updateOrFail lancerebbe eccezione, usiamo update() con where() per sicurezza.
            $locked = Post::where('id', $post->id)
                ->where('publication_status', PublicationStatus::Scheduled)
                ->update(['publication_status' => PublicationStatus::Publishing]);

            if ($locked === 0) {
                // Un altro worker ha già preso in carico questo post — saltiamo.
                Log::info("Scheduler: post {$post->id} già in elaborazione da altro worker, skippato");
                continue;
            }

            Log::info("Scheduler: dispatch PublishPostJob per post {$post->id}");
            PublishPostJob::dispatch($post->id);
        }
    }
}
