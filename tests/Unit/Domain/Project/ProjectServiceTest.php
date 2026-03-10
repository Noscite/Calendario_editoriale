<?php

declare(strict_types=1);

use App\Domain\Brand\Contracts\BrandRepositoryInterface;
use App\Domain\Post\Contracts\PostRepositoryInterface;
use App\Domain\Project\Contracts\ProjectRepositoryInterface;
use App\Domain\Project\Enums\ProjectStatus;
use App\Domain\Project\Models\Project;
use App\Domain\Project\Services\ProjectService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->projectRepo = Mockery::mock(ProjectRepositoryInterface::class);
    $this->brandRepo = Mockery::mock(BrandRepositoryInterface::class);
    $this->postRepo = Mockery::mock(PostRepositoryInterface::class);
    $this->service = new ProjectService($this->projectRepo, $this->brandRepo, $this->postRepo);
});

afterEach(fn () => Mockery::close());

describe('create', function () {
    it('creates with Draft status and validates brand exists', function () {
        $this->brandRepo->shouldReceive('findOrFail')->with(1)->once();

        $project = new Project([
            'brand_id' => 1,
            'name' => 'Test Project',
            'status' => ProjectStatus::Draft,
        ]);

        $this->projectRepo
            ->shouldReceive('create')
            ->once()
            ->withArgs(fn (array $d) => $d['status'] === ProjectStatus::Draft)
            ->andReturn($project);

        $result = $this->service->create(['brand_id' => 1, 'name' => 'Test Project']);

        expect($result->status)->toBe(ProjectStatus::Draft);
    });
});

describe('status transitions', function () {
    $validTransitions = [
        ['draft', 'generating'],
        ['generating', 'review'],
        ['generating', 'draft'],
        ['review', 'approved'],
        ['review', 'draft'],
        ['approved', 'published'],
        ['approved', 'review'],
        ['published', 'review'],
    ];

    foreach ($validTransitions as [$from, $to]) {
        it("allows transition from {$from} to {$to}", function () use ($from, $to) {
            $project = new Project([
                'name' => 'Test',
                'status' => ProjectStatus::from($from),
            ]);
            $project->id = 1;

            $updatedProject = new Project([
                'name' => 'Test',
                'status' => ProjectStatus::from($to),
            ]);

            $this->projectRepo
                ->shouldReceive('findOrFail')
                ->with(1)
                ->once()
                ->andReturn($project);

            $this->projectRepo
                ->shouldReceive('updateStatus')
                ->once()
                ->andReturn($updatedProject);

            $result = $this->service->updateStatus(1, ProjectStatus::from($to));

            expect($result->status)->toBe(ProjectStatus::from($to));
        });
    }

    $invalidTransitions = [
        ['draft', 'review'],
        ['draft', 'approved'],
        ['draft', 'published'],
        ['generating', 'approved'],
        ['generating', 'published'],
        ['review', 'generating'],
        ['review', 'published'],
        ['approved', 'draft'],
        ['approved', 'generating'],
        ['published', 'draft'],
        ['published', 'generating'],
        ['published', 'approved'],
    ];

    foreach ($invalidTransitions as [$from, $to]) {
        it("rejects transition from {$from} to {$to}", function () use ($from, $to) {
            $project = new Project([
                'name' => 'Test',
                'status' => ProjectStatus::from($from),
            ]);
            $project->id = 1;

            $this->projectRepo
                ->shouldReceive('findOrFail')
                ->with(1)
                ->once()
                ->andReturn($project);

            $this->service->updateStatus(1, ProjectStatus::from($to));
        })->throws(ValidationException::class);
    }
});

describe('update with status', function () {
    it('validates status transition when status field is included', function () {
        $project = new Project(['name' => 'Test', 'status' => ProjectStatus::Draft]);
        $project->id = 1;

        $this->projectRepo
            ->shouldReceive('findOrFail')
            ->with(1)
            ->once()
            ->andReturn($project);

        $this->projectRepo
            ->shouldReceive('update')
            ->once()
            ->andReturn(new Project(['name' => 'Test', 'status' => ProjectStatus::Generating]));

        $result = $this->service->update(1, ['status' => 'generating']);

        expect($result->status)->toBe(ProjectStatus::Generating);
    });

    it('rejects invalid status transition during update', function () {
        $project = new Project(['name' => 'Test', 'status' => ProjectStatus::Draft]);
        $project->id = 1;

        $this->projectRepo
            ->shouldReceive('findOrFail')
            ->with(1)
            ->once()
            ->andReturn($project);

        $this->service->update(1, ['status' => 'approved']);
    })->throws(ValidationException::class);
});

describe('duplicate', function () {
    it('creates a copy with Draft status and (copia) suffix', function () {
        $original = new Project([
            'brand_id' => 1,
            'name' => 'Original',
            'start_date' => '2026-01-01',
            'end_date' => '2026-02-01',
            'platforms' => ['instagram'],
            'brief' => 'Test brief',
        ]);
        $original->id = 5;

        $this->projectRepo
            ->shouldReceive('findOrFail')
            ->with(5)
            ->once()
            ->andReturn($original);

        $this->projectRepo
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $data) {
                return str_contains($data['name'], '(copia)')
                    && $data['status'] === ProjectStatus::Draft;
            })
            ->andReturn(new Project(['name' => 'Original (copia)', 'status' => ProjectStatus::Draft]));

        $result = $this->service->duplicate(5);

        expect($result->name)->toContain('(copia)');
        expect($result->status)->toBe(ProjectStatus::Draft);
    });
});

describe('delete', function () {
    it('deletes a project', function () {
        $project = new Project(['name' => 'To Delete']);
        $project->id = 3;

        $this->projectRepo->shouldReceive('findOrFail')->with(3)->once()->andReturn($project);
        $this->projectRepo->shouldReceive('delete')->with($project)->once()->andReturn(true);

        $this->service->delete(3);

        expect(true)->toBeTrue();
    });
});
