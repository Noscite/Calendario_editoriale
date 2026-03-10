<?php

declare(strict_types=1);

use App\Domain\Post\Enums\PublicationStatus;
use App\Domain\Post\Models\Post;
use App\Domain\Social\Jobs\PublishPostJob;
use App\Domain\Social\Jobs\PublishScheduledPostsJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

/**
 * Test per PublishScheduledPostsJob.
 *
 * Verifica:
 *   - La finestra temporale ±2 minuti funziona correttamente
 *   - Il lock ottimistico con publication_status = 'publishing' evita doppia pubblicazione
 *   - I post già in stato 'publishing' vengono saltati
 */
describe('PublishScheduledPostsJob', function () {

    beforeEach(function () {
        Bus::fake();
    });

    it('dispatcha PublishPostJob per post nella finestra temporale', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand   = createBrand($org);
        $project = createProject($brand);

        // Post schedulato per ora corrente
        $post = createPost($project, [
            'scheduled_date'     => now()->toDateString(),
            'scheduled_time'     => now()->format('H:i'),
            'publication_status' => PublicationStatus::Scheduled->value,
        ]);

        (new PublishScheduledPostsJob())->handle();

        Bus::assertDispatched(PublishPostJob::class, function ($job) use ($post) {
            return true; // Il job è stato dispatched
        });
    });

    it('dispatcha post nella finestra +2 minuti', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand   = createBrand($org);
        $project = createProject($brand);

        // Post schedulato 1 minuto dopo
        $scheduledTime = now()->addMinutes(1)->format('H:i');
        $post = createPost($project, [
            'scheduled_date'     => now()->toDateString(),
            'scheduled_time'     => $scheduledTime,
            'publication_status' => PublicationStatus::Scheduled->value,
        ]);

        (new PublishScheduledPostsJob())->handle();

        Bus::assertDispatched(PublishPostJob::class);
    });

    it('NON dispatcha post fuori dalla finestra temporale', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand   = createBrand($org);
        $project = createProject($brand);

        // Post schedulato 10 minuti dopo — fuori dalla finestra ±2
        $scheduledTime = now()->addMinutes(10)->format('H:i');
        $post = createPost($project, [
            'scheduled_date'     => now()->toDateString(),
            'scheduled_time'     => $scheduledTime,
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
            'scheduled_date'     => now()->toDateString(),
            'scheduled_time'     => now()->format('H:i'),
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
            'scheduled_date'     => now()->toDateString(),
            'scheduled_time'     => now()->format('H:i'),
            'publication_status' => PublicationStatus::Scheduled->value,
        ]);

        (new PublishScheduledPostsJob())->handle();

        // Verifica che il post sia stato marcato come 'publishing'
        $post->refresh();
        expect($post->publication_status)->toBe(PublicationStatus::Publishing);
    });

    it('ignora post schedulati per date diverse da oggi', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand   = createBrand($org);
        $project = createProject($brand);

        $post = createPost($project, [
            'scheduled_date'     => now()->addDays(1)->toDateString(), // domani
            'scheduled_time'     => now()->format('H:i'),
            'publication_status' => PublicationStatus::Scheduled->value,
        ]);

        (new PublishScheduledPostsJob())->handle();

        Bus::assertNotDispatched(PublishPostJob::class);
    });
});
