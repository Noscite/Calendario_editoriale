<?php

declare(strict_types=1);

use App\Domain\Subscription\Services\PostCreditService;

beforeEach(function () {
    [, $this->org] = createAuthenticatedUser();
    $this->service = app(PostCreditService::class);
});

it('has zero balance and is not enrolled before any movement', function () {
    expect($this->service->isWalletEnrolled($this->org->id))->toBeFalse();
    expect($this->service->balance($this->org->id))->toBe(0);
});

it('becomes enrolled after the first credit and balance reflects the ledger sum', function () {
    $this->service->credit($this->org->id, 100, 'purchase');

    expect($this->service->isWalletEnrolled($this->org->id))->toBeTrue();
    expect($this->service->balance($this->org->id))->toBe(100);

    $this->service->debit($this->org->id, 3);

    expect($this->service->balance($this->org->id))->toBe(97);
});

it('rejects crediting a non-positive amount', function () {
    $this->service->credit($this->org->id, 0, 'purchase');
})->throws(InvalidArgumentException::class);

it('ignores debit calls with count <= 0', function () {
    $this->service->credit($this->org->id, 10, 'purchase');
    $this->service->debit($this->org->id, 0);

    expect($this->service->balance($this->org->id))->toBe(10);
});

it('hasSufficientCredit is true for organizations never enrolled in the wallet', function () {
    // Nessun movimento mai fatto: il wallet non riguarda questa org,
    // niente blocco anche se il saldo "letto" sarebbe 0.
    expect($this->service->hasSufficientCredit($this->org->id, 50))->toBeTrue();
});

it('hasSufficientCredit gates correctly once enrolled', function () {
    $this->service->credit($this->org->id, 10, 'purchase');

    expect($this->service->hasSufficientCredit($this->org->id, 10))->toBeTrue();
    expect($this->service->hasSufficientCredit($this->org->id, 11))->toBeFalse();
});

it('estimatePostsForProject sums posts_per_week across platforms and weeks', function () {
    $brand = createBrand($this->org);
    $project = createProject($brand, [
        'platforms' => ['linkedin', 'instagram'],
        'posts_per_week' => ['linkedin' => 3, 'instagram' => 2],
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-14', // 14 giorni = 2 settimane esatte
    ]);

    // (3 + 2) * 2 settimane = 10
    expect($this->service->estimatePostsForProject($project))->toBe(10);
});

it('estimatePostsForProject defaults to 2 posts/week per platform when unset', function () {
    $brand = createBrand($this->org);
    $project = createProject($brand, [
        'platforms' => ['linkedin'],
        'posts_per_week' => [],
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-07', // 1 settimana
    ]);

    expect($this->service->estimatePostsForProject($project))->toBe(2);
});
