<?php

declare(strict_types=1);

use App\Domain\Generation\Services\AnthropicApiClient;
use App\Domain\Generation\Services\AnthropicRateLimiter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Test per AnthropicRateLimiter.
 *
 * Verifica il comportamento del rate limiter per organizzazione.
 * Le chiamate HTTP verso Anthropic sono intercettate con Http::fake().
 */
describe('AnthropicRateLimiter', function () {

    beforeEach(function () {
        RateLimiter::clear('anthropic:1');
        RateLimiter::clear('anthropic:2');
        RateLimiter::clear('anthropic:5');
        RateLimiter::clear('anthropic:10');
        RateLimiter::clear('anthropic:20');
        RateLimiter::clear('anthropic:99');
        RateLimiter::clear('anthropic:100');
        RateLimiter::clear('anthropic:200');

        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response([
                'content' => [['type' => 'text', 'text' => 'risposta di test']],
                'usage'   => ['input_tokens' => 10, 'output_tokens' => 5],
            ], 200),
        ]);

        $this->apiClient = new AnthropicApiClient();
        $this->limiter   = new AnthropicRateLimiter($this->apiClient);
    });

    it('call() delega ad AnthropicApiClient e restituisce la risposta', function () {
        $result = $this->limiter->call(1, 'prompt test', 500);

        expect($result)
            ->toBeArray()
            ->toHaveKey('content');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'anthropic.com'));
    });

    it('isThrottled() restituisce false quando sotto il limite', function () {
        expect($this->limiter->isThrottled(1))->toBeFalse();
    });

    it('isThrottled() restituisce true quando il limite è raggiunto', function () {
        config(['services.anthropic.requests_per_minute' => 2]);
        $limiter = new AnthropicRateLimiter($this->apiClient);

        RateLimiter::hit('anthropic:99', 60);
        RateLimiter::hit('anthropic:99', 60);

        expect($limiter->isThrottled(99))->toBeTrue();
    });

    it('remaining() restituisce il numero corretto di tentativi rimanenti', function () {
        config(['services.anthropic.requests_per_minute' => 5]);
        $limiter = new AnthropicRateLimiter($this->apiClient);

        expect($limiter->remaining(10))->toBe(5);

        RateLimiter::hit('anthropic:10', 60);
        expect($limiter->remaining(10))->toBe(4);

        RateLimiter::hit('anthropic:10', 60);
        RateLimiter::hit('anthropic:10', 60);
        expect($limiter->remaining(10))->toBe(2);
    });

    it('remaining() non scende sotto zero', function () {
        config(['services.anthropic.requests_per_minute' => 1]);
        $limiter = new AnthropicRateLimiter($this->apiClient);

        // Supera il limite
        RateLimiter::hit('anthropic:20', 60);
        RateLimiter::hit('anthropic:20', 60);
        RateLimiter::hit('anthropic:20', 60);

        expect($limiter->remaining(20))->toBe(0);
    });

    it('availableIn() restituisce 0 quando non throttled', function () {
        expect($this->limiter->availableIn(1))->toBe(0);
    });

    it('call() registra un hit nel RateLimiter dopo ogni chiamata', function () {
        config(['services.anthropic.requests_per_minute' => 10]);
        $limiter = new AnthropicRateLimiter($this->apiClient);

        $limiter->call(5, 'primo prompt', 100);
        expect($limiter->remaining(5))->toBe(9);

        $limiter->call(5, 'secondo prompt', 100);
        expect($limiter->remaining(5))->toBe(8);
    });

    it('call() isola il rate limit per organizzazione', function () {
        config(['services.anthropic.requests_per_minute' => 3]);
        $limiter = new AnthropicRateLimiter($this->apiClient);

        $limiter->call(100, 'prompt org 1', 100);

        expect($limiter->remaining(100))->toBe(2);
        expect($limiter->remaining(200))->toBe(3);
    });
});
