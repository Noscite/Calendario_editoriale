<?php

use App\Domain\Social\Jobs\PublishScheduledPostsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Scheduler ─────────────────────────────────────────────────────
// Replica esatta di scheduler_service.py → run_scheduler()
// Controlla ogni minuto se ci sono post da pubblicare (HH:MM match)
Schedule::job(new PublishScheduledPostsJob)->everyMinute();
