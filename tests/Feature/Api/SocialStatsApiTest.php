<?php

declare(strict_types=1);

use App\Domain\Post\Enums\Platform;
use App\Domain\Social\Models\PostPublication;
use App\Domain\Social\Models\SocialConnection;
use App\Domain\Social\Models\SocialMetric;

/**
 * Regression test per SocialStatsController::brandStats.
 *
 * Bug fixato: il controller cercava metric account-level
 * (post_publication_id IS NULL), ma CollectSocialMetricsJob scrive sempre
 * con post_publication_id valorizzato (per-post). Risultato: insights
 * sempre a 0 lato UI.
 *
 * Fix: aggregazione SUM delle metric per-post nel periodo, filtrato per
 * metric_date (= published_at del post).
 */

function makeConnection($brand, string $platformValue, array $overrides = []): SocialConnection
{
    return SocialConnection::create(array_merge([
        'brand_id'              => $brand->id,
        'platform'              => $platformValue,
        'access_token'          => 'tok-' . \Illuminate\Support\Str::random(8),
        'external_account_id'   => 'ext-' . \Illuminate\Support\Str::random(6),
        'external_account_name' => 'Acme Page',
        'is_active'             => true,
    ], $overrides));
}

function makePublication($post, SocialConnection $conn, ?\Carbon\Carbon $publishedAt = null): PostPublication
{
    return PostPublication::create([
        'post_id'              => $post->id,
        'social_connection_id' => $conn->id,
        'status'               => 'published',
        'published_at'         => $publishedAt ?? now(),
        'external_post_id'     => 'pub-' . \Illuminate\Support\Str::random(8),
    ]);
}

function makeMetric(SocialConnection $conn, ?PostPublication $pub, array $values, ?\Carbon\Carbon $metricDate = null): SocialMetric
{
    return SocialMetric::create(array_merge([
        'social_connection_id' => $conn->id,
        'post_publication_id'  => $pub?->id,
        'metric_date'          => $metricDate ?? now(),
        'metric_type'          => 'daily',
    ], $values));
}

describe('GET /api/social/stats/brand/{brand_id}', function () {
    it('aggregates per-post metrics with SUM (regression: insights were always 0)', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand        = createBrand($org);
        $project      = createProject($brand);

        $conn = makeConnection($brand, Platform::LinkedIn->value);

        // 3 post pubblicati, ciascuno con la sua SocialMetric per-post
        $pub1 = makePublication(createPost($project), $conn);
        $pub2 = makePublication(createPost($project), $conn);
        $pub3 = makePublication(createPost($project), $conn);

        makeMetric($conn, $pub1, ['impressions' => 100, 'reach' => 80,  'engagement' => 10, 'likes' => 5,  'comments' => 2, 'shares' => 1, 'clicks' => 3]);
        makeMetric($conn, $pub2, ['impressions' => 200, 'reach' => 160, 'engagement' => 25, 'likes' => 15, 'comments' => 4, 'shares' => 2, 'clicks' => 6]);
        makeMetric($conn, $pub3, ['impressions' => 50,  'reach' => 40,  'engagement' => 5,  'likes' => 3,  'comments' => 1, 'shares' => 0, 'clicks' => 1]);

        $response = $this->actingAs($user)
            ->getJson("/api/social/stats/brand/{$brand->id}?days=30");

        $response->assertOk();
        $platforms = $response->json('platforms');
        expect($platforms)->toHaveCount(1);

        $platform = $platforms[0];
        expect($platform['platform'])->toBe('linkedin')
            ->and($platform['impressions'])->toBe(350)   // 100 + 200 + 50
            ->and($platform['reach'])->toBe(280)         // 80 + 160 + 40
            ->and($platform['engagement'])->toBe(40)     // 10 + 25 + 5
            ->and($platform['likes'])->toBe(23)          // 5 + 15 + 3
            ->and($platform['comments'])->toBe(7)        // 2 + 4 + 1
            ->and($platform['shares'])->toBe(3)          // 1 + 2 + 0
            ->and($platform['clicks'])->toBe(10);        // 3 + 6 + 1
    });

    it('respects metric_date period filter (excludes old metrics outside window)', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand        = createBrand($org);
        $project      = createProject($brand);
        $conn         = makeConnection($brand, Platform::Facebook->value);

        // Post pubblicato dentro la finestra (oggi)
        $pubRecent = makePublication(createPost($project), $conn, now());
        makeMetric($conn, $pubRecent, ['impressions' => 100, 'likes' => 10], now());

        // Post pubblicato 60 giorni fa: fuori finestra "ultimi 30 giorni"
        $pubOld = makePublication(createPost($project), $conn, now()->subDays(60));
        makeMetric($conn, $pubOld, ['impressions' => 999, 'likes' => 999], now()->subDays(60));

        $response = $this->actingAs($user)
            ->getJson("/api/social/stats/brand/{$brand->id}?days=30");

        $platform = $response->json('platforms')[0];
        expect($platform['impressions'])->toBe(100)
            ->and($platform['likes'])->toBe(10);
    });

    it('returns zeros (not null) when no metrics exist for the connection', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand        = createBrand($org);
        makeConnection($brand, Platform::LinkedIn->value);

        $response = $this->actingAs($user)
            ->getJson("/api/social/stats/brand/{$brand->id}?days=30");

        $response->assertOk();
        $platform = $response->json('platforms')[0];
        expect($platform['platform'])->toBe('linkedin')
            ->and($platform['impressions'])->toBe(0)
            ->and($platform['likes'])->toBe(0)
            ->and($platform['followers_count'])->toBeNull();
    });

    it('skips inactive connections', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand        = createBrand($org);
        makeConnection($brand, Platform::LinkedIn->value, ['is_active' => true]);
        makeConnection($brand, Platform::Facebook->value, ['is_active' => false]);

        $response = $this->actingAs($user)
            ->getJson("/api/social/stats/brand/{$brand->id}?days=30");

        $response->assertOk();
        $platforms = $response->json('platforms');
        expect($platforms)->toHaveCount(1)
            ->and($platforms[0]['platform'])->toBe('linkedin');
    });

    it('computes engagement_rate from aggregated reach + engagement', function () {
        [$user, $org] = createAuthenticatedUser();
        $brand        = createBrand($org);
        $project      = createProject($brand);
        $conn         = makeConnection($brand, Platform::LinkedIn->value);

        $pub = makePublication(createPost($project), $conn);
        makeMetric($conn, $pub, ['impressions' => 1000, 'reach' => 500, 'engagement' => 50]);

        $response = $this->actingAs($user)
            ->getJson("/api/social/stats/brand/{$brand->id}?days=30");

        // engagement_rate = engagement/reach * 100 = 50/500 * 100 = 10
        expect((float) $response->json('engagement_rate'))->toBe(10.0);
    });
});
