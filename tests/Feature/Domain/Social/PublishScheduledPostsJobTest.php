<?php

declare(strict_types=1);

use App\Domain\Post\Enums\PublicationStatus;
use App\Domain\Post\Models\Post;
use App\Domain\Social\Jobs\PublishPostJob;
use App\Domain\Social\Jobs\PublishScheduledPostsJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;

/**
 * Test per PublishScheduledPostsJob.
 *
 * Verifica:
 *   - La finestra temporale ±2 minuti viene calcolata in Europe/Rome
 *     (scheduled_time è salvato come ora locale italiana, anche se il
 *     timezone applicativo è UTC).
 *   - Il lock ottimistico con publication_status = 'publishing' evita doppia pubblicazione
 *   - I post già in stato 'publishing' vengono saltati
 */
describe('PublishScheduledPostsJob', function () {

    beforeEach(function () {
        Bus::fake();

        // 12:30 UTC == 14:30 Europe/Rome (CEST, ora legale)
        Carbon::setTestNow(Carbon::create(2026, 5, 7, 12, 30, 0, 'UTC'));
    });

    afterEach(function () {
        Carbon::setTestNow();
    });

    it('dispatcha PublishPostJob per post nella finestra Rome (14:30)', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand   = createBrand($org);
        $project = createProject($brand);

        // 12:30 UTC = 14:30 Europe/Rome → match esatto
        $post = createPost($project, [
            'scheduled_date'     => '2026-05-07',
            'scheduled_time'     => '14:30',
            'publication_status' => PublicationStatus::Scheduled->value,
        ]);

        (new PublishScheduledPostsJob())->handle();

        Bus::assertDispatched(PublishPostJob::class);
    });

    it('dispatcha post nella finestra +2 minuti Rome', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand   = createBrand($org);
        $project = createProject($brand);

        // 14:31 Rome → dentro [14:28 - 14:32]
        $post = createPost($project, [
            'scheduled_date'     => '2026-05-07',
            'scheduled_time'     => '14:31',
            'publication_status' => PublicationStatus::Scheduled->value,
        ]);

        (new PublishScheduledPostsJob())->handle();

        Bus::assertDispatched(PublishPostJob::class);
    });

    it('NON dispatcha post fuori dalla finestra Rome (12:25 UTC = 14:25 Rome, post 14:30)', function () {
        // Stesso post (14:30 Rome) ma "ora corrente" anticipata di 5 minuti rispetto allo schedule.
        // 12:25 UTC = 14:25 Rome, finestra [14:23 - 14:27], post 14:30 → fuori.
        Carbon::setTestNow(Carbon::create(2026, 5, 7, 12, 25, 0, 'UTC'));

        [$user, $org] = createAuthenticatedUser();
        $brand   = createBrand($org);
        $project = createProject($brand);

        $post = createPost($project, [
            'scheduled_date'     => '2026-05-07',
            'scheduled_time'     => '14:30',
            'publication_status' => PublicationStatus::Scheduled->value,
        ]);

        (new PublishScheduledPostsJob())->handle();

        Bus::assertNotDispatched(PublishPostJob::class);
    });

    it('NON dispatcha quando server UTC = ora schedulata ma diversa da Rome', function () {
        // Regressione del bug: il vecchio scheduler usava UTC, quindi un post
        // schedulato per "12:30" (interpretato come Rome dall'utente) veniva
        // pubblicato 2h prima quando l'orologio UTC segnava 12:30.
        // Con il fix, alle 12:30 UTC (= 14:30 Rome) NON deve scattare un post 12:30.
        [$user, $org] = createAuthenticatedUser();
        $brand   = createBrand($org);
        $project = createProject($brand);

        $post = createPost($project, [
            'scheduled_date'     => '2026-05-07',
            'scheduled_time'     => '12:30', // utente intende 12:30 Rome
            'publication_status' => PublicationStatus::Scheduled->value,
        ]);

        (new PublishScheduledPostsJob())->handle();

        Bus::assertNotDispatched(PublishPostJob::class);
    });

    it('NON dispatcha post già in stato publishing (lock ottimistico)', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand   = createBrand($org);
        $project = createProject($brand);

        // Post già in stato 'publishing' — altro worker lo ha preso
        $post = createPost($project, [
            'scheduled_date'     => '2026-05-07',
            'scheduled_time'     => '14:30',
            'publication_status' => PublicationStatus::Publishing->value,
        ]);

        (new PublishScheduledPostsJob())->handle();

        Bus::assertNotDispatched(PublishPostJob::class);
    });

    it('aggiorna status a publishing prima del dispatch', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand   = createBrand($org);
        $project = createProject($brand);

        $post = createPost($project, [
            'scheduled_date'     => '2026-05-07',
            'scheduled_time'     => '14:30',
            'publication_status' => PublicationStatus::Scheduled->value,
        ]);

        (new PublishScheduledPostsJob())->handle();

        $post->refresh();
        expect($post->publication_status)->toBe(PublicationStatus::Publishing);
    });

    it('ignora post schedulati per date diverse da oggi (Rome)', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand   = createBrand($org);
        $project = createProject($brand);

        $post = createPost($project, [
            'scheduled_date'     => '2026-05-08', // domani Rome
            'scheduled_time'     => '14:30',
            'publication_status' => PublicationStatus::Scheduled->value,
        ]);

        (new PublishScheduledPostsJob())->handle();

        Bus::assertNotDispatched(PublishPostJob::class);
    });
});
