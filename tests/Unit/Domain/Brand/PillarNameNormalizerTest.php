<?php

declare(strict_types=1);

use App\Domain\Brand\Support\PillarNameNormalizer;

describe('PillarNameNormalizer::normalize', function () {
    it('returns empty string for null/empty input', function () {
        expect(PillarNameNormalizer::normalize(null))->toBe('');
        expect(PillarNameNormalizer::normalize(''))->toBe('');
        expect(PillarNameNormalizer::normalize('   '))->toBe('');
    });

    it('lowercases and folds accents', function () {
        expect(PillarNameNormalizer::normalize('Frontièra Tècnica'))->toBe('frontiera tecnica');
        expect(PillarNameNormalizer::normalize('Manifèsto'))->toBe('manifesto');
    });

    it('collapses spaces and underscores', function () {
        expect(PillarNameNormalizer::normalize('frontiera__tecnica'))->toBe('frontiera tecnica');
        expect(PillarNameNormalizer::normalize('frontiera   tecnica'))->toBe('frontiera tecnica');
        expect(PillarNameNormalizer::normalize('  frontiera_ _tecnica  '))->toBe('frontiera tecnica');
    });

    it('strips punctuation but keeps alphanumeric', function () {
        expect(PillarNameNormalizer::normalize('Backstage / dietro le quinte'))->toBe('backstage dietro le quinte');
        expect(PillarNameNormalizer::normalize('AI 2.0'))->toBe('ai 2 0');
    });
});

describe('PillarNameNormalizer::equals', function () {
    it('returns true for normalized-equivalent names', function () {
        expect(PillarNameNormalizer::equals('Frontiera Tecnica', 'frontiera_tecnica'))->toBeTrue();
        expect(PillarNameNormalizer::equals('Manifesto!', 'MANIFESTO'))->toBeTrue();
    });

    it('returns false for different names', function () {
        expect(PillarNameNormalizer::equals('Frontiera Tecnica', 'Backstage'))->toBeFalse();
    });

    it('returns false when one input is empty', function () {
        expect(PillarNameNormalizer::equals('', 'Manifesto'))->toBeFalse();
        expect(PillarNameNormalizer::equals('Manifesto', null))->toBeFalse();
        expect(PillarNameNormalizer::equals(null, null))->toBeFalse();
    });
});
