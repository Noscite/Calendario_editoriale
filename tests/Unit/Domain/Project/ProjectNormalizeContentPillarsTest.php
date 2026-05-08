<?php

declare(strict_types=1);

use App\Domain\Project\Models\Project;

/**
 * Unit test per Project::normalizeContentPillarsList.
 *
 * Defensive auto-healer per il bug del primo rollout di EditProjectWizardV2
 * (PR-WIZARD-2) che salvava content_pillars come array di {name, description}
 * objects invece di strings. Vedi commit hotfix dopo d6afb82.
 */

describe('Project::normalizeContentPillarsList', function () {
    it('ritorna [] per input non-array', function () {
        expect(Project::normalizeContentPillarsList(null))->toBe([]);
        expect(Project::normalizeContentPillarsList(''))->toBe([]);
        expect(Project::normalizeContentPillarsList('foo'))->toBe([]);
        expect(Project::normalizeContentPillarsList(42))->toBe([]);
    });

    it('preserva array di stringhe (shape canonica legacy)', function () {
        expect(Project::normalizeContentPillarsList(['Educational', 'Behind the Scenes', 'Tips']))
            ->toBe(['Educational', 'Behind the Scenes', 'Tips']);
    });

    it('estrae name da array di {name, description} objects (shape erronea PR-WIZARD-2)', function () {
        $input = [
            ['name' => 'Frontiera tecnica', 'description' => 'lorem ipsum'],
            ['name' => 'Manifesto',         'description' => 'altro testo'],
        ];
        expect(Project::normalizeContentPillarsList($input))->toBe(['Frontiera tecnica', 'Manifesto']);
    });

    it('gestisce shape mista (strings + objects)', function () {
        $input = [
            'Educational',
            ['name' => 'Backstage', 'description' => 'd'],
            'Tips',
        ];
        expect(Project::normalizeContentPillarsList($input))->toBe(['Educational', 'Backstage', 'Tips']);
    });

    it('trim e skip di name vuoti/whitespace-only', function () {
        $input = [
            '   ',
            'Valido',
            ['name' => '', 'description' => 'd'],
            ['name' => '  Trimmed  '],
            '',
        ];
        expect(Project::normalizeContentPillarsList($input))->toBe(['Valido', 'Trimmed']);
    });

    it('gestisce stdClass objects (defensive contro JSON casting Laravel)', function () {
        $obj = new stdClass();
        $obj->name = 'Ondalium';
        expect(Project::normalizeContentPillarsList([$obj, 'Plain']))
            ->toBe(['Ondalium', 'Plain']);
    });
});
