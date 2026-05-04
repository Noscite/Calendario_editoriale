<?php

use App\Domain\Review\Jobs\FetchGoogleReviewsJob;
use App\Domain\Social\Jobs\CollectSocialMetricsJob;
use App\Domain\Social\Jobs\PublishScheduledPostsJob;
use App\Domain\Social\Models\SocialConnection;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Scheduler ─────────────────────────────────────────────────────
// Replica esatta di scheduler_service.py → run_scheduler()
// Controlla ogni minuto se ci sono post da pubblicare (finestra ±2 min)
Schedule::job(new PublishScheduledPostsJob)->everyMinute();

// ── Social Metrics ────────────────────────────────────────────────
// Raccolta metriche da Facebook/LinkedIn ogni 6 ore
Schedule::job(new CollectSocialMetricsJob)->everySixHours();

// ── Trial Reminder ───────────────────────────────────────────────
// Invia email di promemoria per trial in scadenza (7, 3, 1 giorno)
Schedule::command('trial:send-reminders')->dailyAt('09:00');

// ── Subscription State Machine ───────────────────────────────────
// Aggiorna trial→pending_payment, active→expired, invia notifiche scadenza
Schedule::command('subscriptions:update-states')->dailyAt('02:00');

// ── Google Reviews Fetch ─────────────────────────────────────────
// Scheduler globale ogni 10 min. Per ogni connection google_business attiva,
// dispatcha il fetch SOLO se è passato l'intervallo configurato sul brand
// (default 30 min, override via brands.review_fetch_interval_minutes).
Schedule::call(function () {
    $connections = SocialConnection::where('platform', 'google_business')
        ->where('is_active', true)
        ->with('brand')
        ->get();

    foreach ($connections as $conn) {
        $interval = $conn->brand?->review_fetch_interval_minutes ?? 30;
        $lastFetch = $conn->last_reviews_fetched_at;

        if (! $lastFetch || now()->diffInMinutes($lastFetch) >= $interval) {
            FetchGoogleReviewsJob::dispatch($conn->id);
        }
    }
})->everyTenMinutes()->name('fetch-google-reviews')->withoutOverlapping();
