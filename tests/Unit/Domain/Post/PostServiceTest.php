<?php

declare(strict_types=1);

use App\Domain\Generation\Contracts\ContentGeneratorInterface;
use App\Domain\Post\Contracts\PostRepositoryInterface;
use App\Domain\Post\Enums\PublicationStatus;
use App\Domain\Post\Models\Post;
use App\Domain\Post\Services\PostService;
use App\Domain\Project\Models\Project;
use App\Domain\Social\Contracts\SocialConnectionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->postRepo = Mockery::mock(PostRepositoryInterface::class);
    $this->socialRepo = Mockery::mock(SocialConnectionRepositoryInterface::class);
    $this->contentGenerator = Mockery::mock(ContentGeneratorInterface::class);
    $this->service = new PostService($this->postRepo, $this->socialRepo, $this->contentGenerator);
});

afterEach(fn () => Mockery::close());

describe('create', function () {
    it('creates a post with default status draft', function () {
        $post = new Post(['content' => 'Hello', 'status' => 'draft', 'publication_status' => 'draft']);

        $this->postRepo
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $data) {
                return $data['status'] === 'draft'
                    && $data['publication_status'] === PublicationStatus::Draft->value;
            })
            ->andReturn($post);

        $result = $this->service->create(['content' => 'Hello']);

        expect($result->status->value)->toBe('draft');
    });

    it('preserves explicit status when provided', function () {
        $post = new Post(['content' => 'OK', 'status' => 'approved']);

        $this->postRepo
            ->shouldReceive('create')
            ->once()
            ->withArgs(fn (array $d) => $d['status'] === 'approved')
            ->andReturn($post);

        $result = $this->service->create(['content' => 'OK', 'status' => 'approved']);

        expect($result->status->value)->toBe('approved');
    });
});

describe('update', function () {
    it('finds and updates a post', function () {
        $post = new Post(['content' => 'Old']);
        $post->id = 1;
        $updated = new Post(['content' => 'New']);

        $this->postRepo->shouldReceive('findOrFail')->with(1)->once()->andReturn($post);
        $this->postRepo->shouldReceive('update')->with($post, ['content' => 'New'])->once()->andReturn($updated);

        $result = $this->service->update(1, ['content' => 'New']);

        expect($result->content)->toBe('New');
    });
});

describe('batchDelete', function () {
    it('deletes multiple posts and returns count', function () {
        $this->postRepo
            ->shouldReceive('deleteMany')
            ->with([1, 2, 3])
            ->once()
            ->andReturn(3);

        $result = $this->service->batchDelete([1, 2, 3]);

        expect($result)->toBe(3);
    });
});

describe('batchReplace', function () {
    it('throws when no posts found', function () {
        $this->postRepo->shouldReceive('findById')->with(999)->once()->andReturn(null);

        $this->service->batchReplace([999], 'brief');
    })->throws(ValidationException::class);

    it('replaces posts with AI-generated content', function () {
        $project = new Project(['id' => 10, 'platforms' => ['instagram']]);
        $project->id = 10;

        $originalPost = new Post([
            'project_id' => 10,
            'platform' => 'instagram',
            'scheduled_date' => '2026-03-01',
            'scheduled_time' => '09:00',
        ]);
        $originalPost->id = 1;
        $originalPost->setRelation('project', $project);

        $this->postRepo->shouldReceive('findById')->with(1)->once()->andReturn($originalPost);

        $generated = new Collection([
            (object) [
                'content' => 'AI generated',
                'hashtags' => ['#test'],
                'pillar' => 'engagement',
                'post_type' => 'educational',
                'visual_suggestion' => 'image',
                'cta' => 'click here',
            ],
        ]);

        $this->contentGenerator
            ->shouldReceive('generateAiPosts')
            ->once()
            ->andReturn($generated);

        $this->postRepo->shouldReceive('deleteMany')->once()->andReturn(1);

        $newPost = new Post(['content' => 'AI generated']);
        $this->postRepo->shouldReceive('create')->once()->andReturn($newPost);

        $result = $this->service->batchReplace([1], 'New brief');

        expect($result)->toHaveCount(1);
        expect($result->first()->content)->toBe('AI generated');
    });
});

describe('regenerate', function () {
    it('delegates to content generator', function () {
        $post = new Post(['content' => 'Regenerated']);

        $this->contentGenerator
            ->shouldReceive('regeneratePost')
            ->with(5, 'custom prompt')
            ->once()
            ->andReturn($post);

        $result = $this->service->regenerate(5, 'custom prompt');

        expect($result->content)->toBe('Regenerated');
    });
});

describe('checkOverlap', function () {
    it('returns overlap data for date range', function () {
        $overlapping = new Collection([
            new Post(['platform' => 'instagram']),
            new Post(['platform' => 'instagram']),
            new Post(['platform' => 'linkedin']),
        ]);

        $allPosts = new Collection(array_fill(0, 10, new Post()));

        $this->postRepo
            ->shouldReceive('findByProjectAndDateRange')
            ->with(1, '2026-01-01', '2026-01-15')
            ->once()
            ->andReturn($overlapping);

        $this->postRepo
            ->shouldReceive('findByProject')
            ->with(1)
            ->once()
            ->andReturn($allPosts);

        $result = $this->service->checkOverlap(1, '2026-01-01', '2026-01-15');

        expect($result['has_overlap'])->toBeTrue();
        expect($result['overlapping_count'])->toBe(3);
        expect($result['kept_count'])->toBe(7);
    });

    it('reports no overlap when date range is clean', function () {
        $this->postRepo
            ->shouldReceive('findByProjectAndDateRange')
            ->once()
            ->andReturn(new Collection());

        $this->postRepo
            ->shouldReceive('findByProject')
            ->once()
            ->andReturn(new Collection(array_fill(0, 5, new Post())));

        $result = $this->service->checkOverlap(1, '2026-06-01', '2026-06-30');

        expect($result['has_overlap'])->toBeFalse();
        expect($result['overlapping_count'])->toBe(0);
    });
});

describe('delete', function () {
    it('deletes a single post', function () {
        $post = new Post();
        $post->id = 7;

        $this->postRepo->shouldReceive('findOrFail')->with(7)->once()->andReturn($post);
        $this->postRepo->shouldReceive('delete')->with($post)->once()->andReturn(true);

        $this->service->delete(7);

        expect(true)->toBeTrue();
    });
});
