<?php

declare(strict_types=1);

use App\Domain\Review\Enums\ReplyStatus;
use App\Domain\Review\Enums\ReviewStatus;
use App\Domain\Review\Jobs\SendReplyJob;
use App\Domain\Review\Models\Review;
use App\Domain\Review\Models\ReviewReply;
use App\Domain\Social\Models\SocialConnection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    // L'observer dispatcha jobs alla creazione delle Review — fakeQueue blocca tutto.
    Queue::fake();
});

/**
 * Crea uno stack minimo Brand+Review e ritorna la Review.
 */
function watchdogMakeReview(array $brandOverrides = []): Review
{
    [, $org] = createAuthenticatedUser();
    $brand   = createBrand($org, $brandOverrides);

    $conn = SocialConnection::create([
        'brand_id'              => $brand->id,
        'platform'              => 'google_business',
        'access_token'          => 'tok',
        'refresh_token'         => 'rtok',
        'token_expires_at'      => now()->addDay(),
        'external_account_id'   => '111',
        'external_account_name' => 'Loc',
        'account_type'          => '222',
        'is_active'             => true,
    ]);

    return Review::withoutGlobalScope('organization')->create([
        'organization_id'      => $org->id,
        'social_connection_id' => $conn->id,
        'brand_id'             => $brand->id,
        'platform'             => 'google_business',
        'external_review_id'   => 'rev-' . uniqid(),
        'reviewer_name'        => 'Mario',
        'rating'               => 5,
        'comment'              => 'Top!',
        'review_created_at'    => now()->subDay(),
        'fetched_at'           => now(),
        'status'               => ReviewStatus::Drafted->value,
        'raw_payload'          => [],
    ]);
}

function watchdogMakeReply(Review $review, array $attrs): ReviewReply
{
    /** @var ReviewReply $reply */
    $reply = ReviewReply::create(array_merge([
        'organization_id'   => $review->organization_id,
        'review_id'         => $review->id,
        'body'              => 'auto-bozza',
        'was_auto_approved' => true,
    ], $attrs));

    // Forza created_at/updated_at se richiesti dal test (Eloquent timestamps
    // ignorano gli override su create() in Laravel 12 — usa raw update).
    $touch = [];
    if (isset($attrs['created_at'])) {
        $touch['created_at'] = $attrs['created_at'];
    }
    if (isset($attrs['updated_at'])) {
        $touch['updated_at'] = $attrs['updated_at'];
    }
    if ($touch !== []) {
        ReviewReply::withoutGlobalScope('organization')
            ->where('id', $reply->id)
            ->update($touch);
        $reply = $reply->fresh();
    }

    return $reply;
}

it('caso A: recupera approved orphan creato da oltre 5 min', function () {
    Bus::fake();

    $review = watchdogMakeReview();
    $reply  = watchdogMakeReply($review, [
        'status'            => ReplyStatus::Approved->value,
        'was_auto_approved' => true,
        'sent_at'           => null,
        'created_at'        => now()->subMinutes(10),
        'updated_at'        => now()->subMinutes(10),
    ]);

    $this->artisan('reviews:watchdog-replies')->assertSuccessful();

    Bus::assertDispatched(SendReplyJob::class, function (SendReplyJob $job) use ($reply): bool {
        $r = new ReflectionClass($job);
        $p = $r->getProperty('reviewReplyId');
        $p->setAccessible(true);
        return $p->getValue($job) === $reply->id;
    });
});

it('caso A negativo: non recupera approved troppo recenti', function () {
    Bus::fake();

    $review = watchdogMakeReview();
    watchdogMakeReply($review, [
        'status'            => ReplyStatus::Approved->value,
        'was_auto_approved' => true,
        'sent_at'           => null,
        'created_at'        => now()->subMinutes(2),
        'updated_at'        => now()->subMinutes(2),
    ]);

    $this->artisan('reviews:watchdog-replies')->assertSuccessful();

    Bus::assertNotDispatched(SendReplyJob::class);
});

it('caso A negativo: non recupera reply manuali', function () {
    Bus::fake();

    $review = watchdogMakeReview();
    watchdogMakeReply($review, [
        'status'            => ReplyStatus::Approved->value,
        'was_auto_approved' => false,
        'sent_at'           => null,
        'created_at'        => now()->subMinutes(20),
        'updated_at'        => now()->subMinutes(20),
    ]);

    $this->artisan('reviews:watchdog-replies')->assertSuccessful();

    Bus::assertNotDispatched(SendReplyJob::class);
});

it('caso B: recupera draft con delay scaduto', function () {
    Bus::fake();

    $review = watchdogMakeReview(['auto_reply_delay_minutes' => 30]);
    $reply  = watchdogMakeReply($review, [
        'status'            => ReplyStatus::Draft->value,
        'was_auto_approved' => true,
        'sent_at'           => null,
        'created_at'        => now()->subMinutes(40), // 30 + 5 buffer = 35 → scaduto
        'updated_at'        => now()->subMinutes(40),
    ]);

    $this->artisan('reviews:watchdog-replies')->assertSuccessful();

    Bus::assertDispatched(SendReplyJob::class, function (SendReplyJob $job) use ($reply): bool {
        $r = new ReflectionClass($job);
        $p = $r->getProperty('reviewReplyId');
        $p->setAccessible(true);
        return $p->getValue($job) === $reply->id;
    });
});

it('caso B negativo: delay non ancora scaduto', function () {
    Bus::fake();

    $review = watchdogMakeReview(['auto_reply_delay_minutes' => 30]);
    watchdogMakeReply($review, [
        'status'            => ReplyStatus::Draft->value,
        'was_auto_approved' => true,
        'sent_at'           => null,
        'created_at'        => now()->subMinutes(20),
        'updated_at'        => now()->subMinutes(20),
    ]);

    $this->artisan('reviews:watchdog-replies')->assertSuccessful();

    Bus::assertNotDispatched(SendReplyJob::class);
});

it('caso C: marca sending stuck come failed', function () {
    $review = watchdogMakeReview();
    $reply  = watchdogMakeReply($review, [
        'status'            => ReplyStatus::Sending->value,
        'was_auto_approved' => true,
        'sent_at'           => null,
        'created_at'        => now()->subMinutes(70),
        'updated_at'        => now()->subMinutes(70),
    ]);

    $this->artisan('reviews:watchdog-replies')->assertSuccessful();

    $fresh = ReviewReply::withoutGlobalScope('organization')->find($reply->id);
    expect($fresh->status)->toBe(ReplyStatus::Failed);
    expect($fresh->error_message)->toContain('Watchdog');
});

it('caso C negativo: sending recente non viene toccato', function () {
    $review = watchdogMakeReview();
    $reply  = watchdogMakeReply($review, [
        'status'            => ReplyStatus::Sending->value,
        'was_auto_approved' => true,
        'sent_at'           => null,
        'created_at'        => now()->subMinutes(30),
        'updated_at'        => now()->subMinutes(30),
    ]);

    $this->artisan('reviews:watchdog-replies')->assertSuccessful();

    $fresh = ReviewReply::withoutGlobalScope('organization')->find($reply->id);
    expect($fresh->status)->toBe(ReplyStatus::Sending);
});

it('dry-run non dispatch e non modifica', function () {
    Bus::fake();

    $review = watchdogMakeReview();
    $reply  = watchdogMakeReply($review, [
        'status'            => ReplyStatus::Approved->value,
        'was_auto_approved' => true,
        'sent_at'           => null,
        'created_at'        => now()->subMinutes(10),
        'updated_at'        => now()->subMinutes(10),
    ]);

    $stuckReply = watchdogMakeReply($review, [
        'status'            => ReplyStatus::Sending->value,
        'was_auto_approved' => true,
        'sent_at'           => null,
        'created_at'        => now()->subMinutes(70),
        'updated_at'        => now()->subMinutes(70),
    ]);

    $this->artisan('reviews:watchdog-replies', ['--dry-run' => true])
        ->expectsOutputToContain('DRY RUN')
        ->assertSuccessful();

    Bus::assertNotDispatched(SendReplyJob::class);

    $stuckFresh = ReviewReply::withoutGlobalScope('organization')->find($stuckReply->id);
    expect($stuckFresh->status)->toBe(ReplyStatus::Sending);
});
