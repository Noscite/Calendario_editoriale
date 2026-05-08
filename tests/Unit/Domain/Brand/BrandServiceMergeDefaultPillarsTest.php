<?php

declare(strict_types=1);

use App\Domain\Brand\Contracts\BrandRepositoryInterface;
use App\Domain\Brand\Models\Brand;
use App\Domain\Brand\Services\BrandService;
use App\Domain\Subscription\Contracts\BillingServiceInterface;

beforeEach(function () {
    $this->brandRepo = Mockery::mock(BrandRepositoryInterface::class);
    $this->billing   = Mockery::mock(BillingServiceInterface::class);
    $this->service   = new BrandService($this->brandRepo, $this->billing);
});

afterEach(fn () => Mockery::close());

/**
 * Stub Brand model con default_content_pillars settabile in-memory.
 */
function makeBrandWithPillars(array $pillars = []): Brand
{
    $brand = new Brand(['name' => 'Test Brand']);
    $brand->default_content_pillars = $pillars;
    return $brand;
}

describe('BrandService::mergeDefaultPillars', function () {
    it('aggiunge pillar a brand con default_content_pillars vuoto', function () {
        $brand = makeBrandWithPillars([]);

        $this->brandRepo
            ->shouldReceive('update')
            ->once()
            ->withArgs(function ($b, $data) {
                return is_array($data['default_content_pillars'] ?? null)
                    && count($data['default_content_pillars']) === 4;
            })
            ->andReturn($brand);

        $result = $this->service->mergeDefaultPillars($brand, [
            ['name' => 'Frontiera tecnica',  'description' => 'd1'],
            ['name' => 'Pattern operativi',  'description' => 'd2'],
            ['name' => 'Manifesto',          'description' => 'd3'],
            ['name' => 'Backstage',          'description' => 'd4'],
        ]);

        expect($result['added_count'])->toBe(4)
            ->and($result['skipped_duplicates'])->toBe(0)
            ->and($result['dropped_count'])->toBe(0)
            ->and($result['pillars'])->toHaveCount(4);
    });

    it('dedup case-insensitive sui nomi esistenti', function () {
        $brand = makeBrandWithPillars([
            ['name' => 'Frontiera tecnica', 'description' => 'esistente1'],
            ['name' => 'Manifesto',         'description' => 'esistente2'],
            ['name' => 'Backstage',         'description' => 'esistente3'],
        ]);

        $this->brandRepo
            ->shouldReceive('update')
            ->once()
            ->andReturn($brand);

        $result = $this->service->mergeDefaultPillars($brand, [
            ['name' => 'frontiera_tecnica', 'description' => 'duplicato'],     // dedup match
            ['name' => 'Pattern Operativi', 'description' => 'd_nuovo'],        // nuovo
        ]);

        expect($result['skipped_duplicates'])->toBe(1)
            ->and($result['added_count'])->toBe(1)
            ->and($result['dropped_count'])->toBe(0)
            ->and($result['pillars'])->toHaveCount(4);

        // Verifica che la description originale del duplicato NON sia stata sovrascritta
        $original = collect($result['pillars'])->firstWhere('name', 'Frontiera tecnica');
        expect($original['description'])->toBe('esistente1');
    });

    it('FIFO drop quando supera il massimo (6)', function () {
        // 5 esistenti
        $existing = [];
        for ($i = 1; $i <= 5; $i++) {
            $existing[] = ['name' => "Pillar {$i}", 'description' => "desc {$i}"];
        }
        $brand = makeBrandWithPillars($existing);

        $this->brandRepo
            ->shouldReceive('update')
            ->once()
            ->andReturn($brand);

        // Aggiungo 3 nuovi → totale virtuale 8 → FIFO drop dei 2 più vecchi
        $result = $this->service->mergeDefaultPillars($brand, [
            ['name' => 'Nuovo A', 'description' => 'a'],
            ['name' => 'Nuovo B', 'description' => 'b'],
            ['name' => 'Nuovo C', 'description' => 'c'],
        ]);

        expect($result['added_count'])->toBe(3)
            ->and($result['dropped_count'])->toBe(2)
            ->and($result['pillars'])->toHaveCount(6);

        $names = array_column($result['pillars'], 'name');
        expect($names)->toContain('Nuovo A', 'Nuovo B', 'Nuovo C')
            ->and($names)->not->toContain('Pillar 1', 'Pillar 2');  // FIFO drop dei più vecchi
    });

    it('no-op se newPillars è vuoto', function () {
        $brand = makeBrandWithPillars([
            ['name' => 'Esistente', 'description' => 'd'],
        ]);

        // Nessuna chiamata a repository->update
        $this->brandRepo->shouldNotReceive('update');

        $result = $this->service->mergeDefaultPillars($brand, []);

        expect($result['added_count'])->toBe(0)
            ->and($result['dropped_count'])->toBe(0)
            ->and($result['skipped_duplicates'])->toBe(0)
            ->and($result['pillars'])->toHaveCount(1);
    });

    it('skippa pillar con nome vuoto/whitespace-only', function () {
        $brand = makeBrandWithPillars([]);

        $this->brandRepo
            ->shouldReceive('update')
            ->once()
            ->andReturn($brand);

        $result = $this->service->mergeDefaultPillars($brand, [
            ['name' => '',         'description' => 'd'],
            ['name' => '   ',      'description' => 'd'],
            ['name' => 'Valido',   'description' => 'd'],
        ]);

        expect($result['added_count'])->toBe(1)
            ->and($result['pillars'])->toHaveCount(1)
            ->and($result['pillars'][0]['name'])->toBe('Valido');
    });
});
