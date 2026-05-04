<?php

declare(strict_types=1);

use App\Domain\Review\Jobs\FetchGoogleReviewsJob;
use App\Domain\Review\Services\GoogleReviewFetcher;
use App\Domain\Social\Exceptions\TokenExpiredException;
use App\Domain\Social\Models\SocialConnection;
use App\Domain\Social\Services\TokenRefreshService;
use Mockery\MockInterface;

function jobGbpConnection(string $platform = 'google_business', bool $active = true): SocialConnection
{
    [, $org] = createAuthenticatedUser();
    $brand = createBrand($org);

    return SocialConnection::create([
        'brand_id'              => $brand->id,
        'platform'              => $platform,
        'access_token'          => 'fake-access',
        'refresh_token'         => 'fake-refresh',
        'token_expires_at'      => now()->addDay(),
        'external_account_id'   => 'acc-id',
        'external_account_name' => 'Acc',
        'account_type'          => 'loc-id',
        'is_active'             => $active,
    ]);
}

it('calls fetcher for an active google connection', function () {
    $conn = jobGbpConnection();

    $this->mock(GoogleReviewFetcher::class, function (MockInterface $m) {
        $m->shouldReceive('fetchAndStore')
            ->once()
            ->andReturn(['fetched' => 0, 'new' => 0, 'updated' => 0, 'errors' => []]);
    });

    (new FetchGoogleReviewsJob($conn->id))->handle(
        app(GoogleReviewFetcher::class),
        app(TokenRefreshService::class),
    );
});

it('skips inactive connections', function () {
    $conn = jobGbpConnection(active: false);

    $this->mock(GoogleReviewFetcher::class, function (MockInterface $m) {
        $m->shouldNotReceive('fetchAndStore');
    });

    (new FetchGoogleReviewsJob($conn->id))->handle(
        app(GoogleReviewFetcher::class),
        app(TokenRefreshService::class),
    );
});

it('skips non-google_business connections', function () {
    $conn = jobGbpConnection(platform: 'linkedin');

    $this->mock(GoogleReviewFetcher::class, function (MockInterface $m) {
        $m->shouldNotReceive('fetchAndStore');
    });

    (new FetchGoogleReviewsJob($conn->id))->handle(
        app(GoogleReviewFetcher::class),
        app(TokenRefreshService::class),
    );
});

it('refreshes token on TokenExpiredException and retries', function () {
    // token_expires_at lontano nel futuro → TokenRefreshService::refreshIfNeeded()
    // ritorna la connection senza chiamare Google OAuth (no need to mock final class).
    $conn = jobGbpConnection();
    $conn->update(['token_expires_at' => now()->addDays(30)]);

    $this->mock(GoogleReviewFetcher::class, function (MockInterface $m) {
        $m->shouldReceive('fetchAndStore')
            ->once()
            ->ordered()
            ->andThrow(new TokenExpiredException('google_business'));
        $m->shouldReceive('fetchAndStore')
            ->once()
            ->ordered()
            ->andReturn(['fetched' => 5, 'new' => 5, 'updated' => 0, 'errors' => []]);
    });

    (new FetchGoogleReviewsJob($conn->id))->handle(
        app(GoogleReviewFetcher::class),
        app(TokenRefreshService::class),
    );
});
