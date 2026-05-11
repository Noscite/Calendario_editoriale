<?php

declare(strict_types=1);

use App\Domain\Generation\Services\GenerationProgressService;

beforeEach(function () {
    $this->service = app(GenerationProgressService::class);
});

it('starts tracking with pending phases', function () {
    $this->service->start(123);

    $state = $this->service->get(123);

    expect($state['status'])->toBe('generating');
    expect($state['phases']['calendar_base']['status'])->toBe('pending');
    expect($state['phases']['territorial_sync']['status'])->toBe('pending');
    expect($state['phases']['territorial_posts']['status'])->toBe('pending');
});

it('updates phase fields with merge', function () {
    $this->service->start(123);
    $this->service->updatePhase(123, 'calendar_base', ['status' => 'running']);
    $this->service->updatePhase(123, 'calendar_base', ['posts_done' => 5]);

    $state = $this->service->get(123);

    expect($state['phases']['calendar_base']['status'])->toBe('running');
    expect($state['phases']['calendar_base']['posts_done'])->toBe(5);
});

it('increments phase counter', function () {
    $this->service->start(123);
    $this->service->updatePhase(123, 'territorial_posts', ['posts_done' => 0]);

    $this->service->incrementPhaseCounter(123, 'territorial_posts', 'posts_done');
    $this->service->incrementPhaseCounter(123, 'territorial_posts', 'posts_done', 3);

    $state = $this->service->get(123);

    expect($state['phases']['territorial_posts']['posts_done'])->toBe(4);
});

it('marks complete', function () {
    $this->service->start(123);
    $this->service->complete(123);

    $state = $this->service->get(123);

    expect($state['status'])->toBe('completed');
    expect($state['completed_at'])->not->toBeNull();
});

it('marks failed with reason', function () {
    $this->service->start(123);
    $this->service->fail(123, 'Claude API rate limit exceeded');

    $state = $this->service->get(123);

    expect($state['status'])->toBe('failed');
    expect($state['failed_reason'])->toBe('Claude API rate limit exceeded');
});

it('returns null for untracked project', function () {
    expect($this->service->get(99999))->toBeNull();
});

it('updatePhase is no-op if not started', function () {
    $this->service->updatePhase(99999, 'calendar_base', ['posts_done' => 100]);
    expect($this->service->get(99999))->toBeNull();
});
