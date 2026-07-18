<?php

declare(strict_types=1);

use App\Domain\Generation\Services\GenerationTracker;
use Illuminate\Support\Facades\Redis;

/**
 * Verifica che il tracker scriva il batch in formato one-indexed
 * (batch in lavorazione = N+1) — sia per il primo batch (no più "Batch 0/5 — 0%")
 * che per il valore finale (deve raggiungere "Batch X/X — 100%").
 *
 * Cfr. plan: /home/ubuntu/.claude/plans/sto-provando-a-rigenerare-twinkly-clock.md
 */
describe('GenerationTracker one-indexed semantics', function () {

    beforeEach(function () {
        Redis::del('generation_status:7777');
    });

    afterEach(function () {
        Redis::del('generation_status:7777');
    });

    it('stores current_batch=1 and percent=20 when the first of 5 batches starts', function () {
        // Simula la chiamata che ClaudeContentGenerator fa per il primo batch
        // dopo la fix: $batchOneIndexed = 0 + 1; percent = (1/5)*100 = 20.
        GenerationTracker::update(7777, 1, 5, 20);

        $state = GenerationTracker::get(7777);

        expect($state)->toBeArray();
        expect($state['current_batch'])->toBe(1);
        expect($state['total_batches'])->toBe(5);
        expect($state['percent'])->toBe(20);
    });

    it('stores 100 percent on the final write', function () {
        // Simula il write finale aggiunto dopo il loop in generateCalendarPosts.
        GenerationTracker::update(7777, 5, 5, 100);

        $state = GenerationTracker::get(7777);

        expect($state['current_batch'])->toBe(5);
        expect($state['percent'])->toBe(100);
    });

    it('returns null after clear', function () {
        GenerationTracker::update(7777, 3, 5, 60);
        GenerationTracker::clear(7777);

        expect(GenerationTracker::get(7777))->toBeNull();
    });
});
