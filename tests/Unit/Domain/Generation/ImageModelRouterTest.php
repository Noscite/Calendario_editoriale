<?php

declare(strict_types=1);

use App\Domain\Generation\Services\ImageModelRouter;

beforeEach(function () {
    $this->router = new ImageModelRouter();
});

describe('ImageModelRouter — plan tier mapping', function () {

    it('Small → mini medium $0.011', function () {
        $r = $this->router->selectFromPlanAndPillar('small', 'educational');
        expect($r['openai_model'])->toBe('gpt-image-1-mini');
        expect($r['quality'])->toBe('medium');
        expect($r['estimated_cost'])->toBe(0.011);
        expect($r['tier'])->toBe('medium');
        expect($r['hero_override'])->toBeFalse();
    });

    it('Standard → gpt-image-1 medium $0.04', function () {
        $r = $this->router->selectFromPlanAndPillar('standard', 'educational');
        expect($r['openai_model'])->toBe('gpt-image-1');
        expect($r['quality'])->toBe('medium');
        expect($r['estimated_cost'])->toBe(0.04);
    });

    it('Pro → gpt-image-1 medium $0.04', function () {
        $r = $this->router->selectFromPlanAndPillar('pro', 'educational');
        expect($r['openai_model'])->toBe('gpt-image-1');
        expect($r['quality'])->toBe('medium');
    });

    it('Unlimited → gpt-image-1 high $0.167', function () {
        $r = $this->router->selectFromPlanAndPillar('unlimited', 'educational');
        expect($r['openai_model'])->toBe('gpt-image-1');
        expect($r['quality'])->toBe('high');
        expect($r['estimated_cost'])->toBe(0.167);
    });

    it('null plan → default high tier', function () {
        $r = $this->router->selectFromPlanAndPillar(null, 'educational');
        expect($r['quality'])->toBe('medium');
        expect($r['openai_model'])->toBe('gpt-image-1');
    });

    it('empty plan → default high tier', function () {
        $r = $this->router->selectFromPlanAndPillar('', 'educational');
        expect($r['tier'])->toBe('high');
    });

    it('unknown plan → default high tier', function () {
        $r = $this->router->selectFromPlanAndPillar('enterprise_plus', 'educational');
        expect($r['tier'])->toBe('high');
    });

    it('case insensitive plan name', function () {
        $r = $this->router->selectFromPlanAndPillar('SMALL', 'educational');
        expect($r['tier'])->toBe('medium');
    });
});

describe('ImageModelRouter — hero pillar override', function () {

    it('Small + lancio → bump a high tier (override active)', function () {
        $r = $this->router->selectFromPlanAndPillar('small', 'lancio libro');
        expect($r['tier'])->toBe('high');
        expect($r['openai_model'])->toBe('gpt-image-1');
        expect($r['quality'])->toBe('medium');
        expect($r['estimated_cost'])->toBe(0.04);
        expect($r['hero_override'])->toBeTrue();
    });

    it('Standard + lancio → resta high (no override needed)', function () {
        $r = $this->router->selectFromPlanAndPillar('standard', 'lancio libro');
        expect($r['tier'])->toBe('high');
        expect($r['hero_override'])->toBeFalse();
    });

    it('Unlimited + lancio → resta premium (no downgrade)', function () {
        $r = $this->router->selectFromPlanAndPillar('unlimited', 'lancio libro');
        expect($r['tier'])->toBe('premium');
        expect($r['hero_override'])->toBeFalse();
    });

    it('hero keywords are case insensitive', function () {
        expect(ImageModelRouter::isHeroPillar('LANCIO'))->toBeTrue();
        expect(ImageModelRouter::isHeroPillar('Big News special'))->toBeTrue();
        expect(ImageModelRouter::isHeroPillar('inaugurazione'))->toBeTrue();
        expect(ImageModelRouter::isHeroPillar('evento speciale'))->toBeTrue();
    });

    it('hero keywords match partially', function () {
        expect(ImageModelRouter::isHeroPillar('post di lancio del libro'))->toBeTrue();
        expect(ImageModelRouter::isHeroPillar('mega launch event'))->toBeTrue();
    });

    it('non-hero pillars do NOT trigger override', function () {
        expect(ImageModelRouter::isHeroPillar('educational'))->toBeFalse();
        expect(ImageModelRouter::isHeroPillar('thought leadership'))->toBeFalse();
        expect(ImageModelRouter::isHeroPillar('behind the scenes'))->toBeFalse();
        expect(ImageModelRouter::isHeroPillar(null))->toBeFalse();
        expect(ImageModelRouter::isHeroPillar(''))->toBeFalse();
    });
});

describe('ImageModelRouter — calendar cost estimation', function () {

    it('Small calendar (25 normal + 5 hero) costs ~$0.48', function () {
        $r1 = $this->router->selectFromPlanAndPillar('small', 'educational');
        $r2 = $this->router->selectFromPlanAndPillar('small', 'lancio libro');
        $total = 25 * $r1['estimated_cost'] + 5 * $r2['estimated_cost'];
        expect($total)->toBeGreaterThan(0.45);
        expect($total)->toBeLessThan(0.50);
    });

    it('Standard calendar costs ~$1.20', function () {
        $r = $this->router->selectFromPlanAndPillar('standard', 'educational');
        $total = 30 * $r['estimated_cost'];
        expect($total)->toBe(1.2);
    });
});
