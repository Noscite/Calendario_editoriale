<?php

declare(strict_types=1);

use App\Domain\Generation\Contracts\ImageGeneratorInterface;
use App\Domain\Subscription\Services\PostCreditService;

beforeEach(function () {
    [$this->user, $this->org] = createAuthenticatedUser();
    $this->brand   = createBrand($this->org);
    $this->project = createProject($this->brand);
    $this->service = app(PostCreditService::class);
});

it('does not debit the first image generation on a post with no image yet', function () {
    $this->service->credit($this->org->id, 5, 'purchase');

    $post = createPost($this->project, ['image_url' => null, 'visual_suggestion' => 'un gatto']);

    $this->mock(ImageGeneratorInterface::class, function ($mock) {
        $mock->shouldReceive('generateImage')->once()->andReturn('https://example.com/img.png');
    });

    $response = $this->actingAs($this->user)->postJson("/api/posts/{$post->id}/generate-image");

    $response->assertOk();
    expect($this->service->balance($this->org->id))->toBe(5); // invariato: prima immagine gratis
});

it('debits 1 credit when regenerating an image on a post that already has one', function () {
    $this->service->credit($this->org->id, 5, 'purchase');

    $post = createPost($this->project, ['image_url' => 'https://example.com/old.png', 'visual_suggestion' => 'un gatto']);

    $this->mock(ImageGeneratorInterface::class, function ($mock) {
        $mock->shouldReceive('generateImage')->once()->andReturn('https://example.com/new.png');
    });

    $response = $this->actingAs($this->user)->postJson("/api/posts/{$post->id}/generate-image");

    $response->assertOk();
    expect($this->service->balance($this->org->id))->toBe(4);
});

it('blocks regeneration with 422 when enrolled and out of credit, without calling the image generator', function () {
    $this->service->credit($this->org->id, 1, 'purchase');
    $this->service->debit($this->org->id, 1); // saldo a 0

    $post = createPost($this->project, ['image_url' => 'https://example.com/old.png', 'visual_suggestion' => 'un gatto']);

    $this->mock(ImageGeneratorInterface::class, function ($mock) {
        $mock->shouldNotReceive('generateImage');
    });

    $response = $this->actingAs($this->user)->postJson("/api/posts/{$post->id}/generate-image");

    $response->assertStatus(422)->assertJsonFragment(['detail' => 'Credito insufficiente per rigenerare l\'immagine. Saldo: 0 post.']);
    expect($this->service->balance($this->org->id))->toBe(0);
});

it('allows regeneration for a non-enrolled organization regardless of implicit zero balance', function () {
    $post = createPost($this->project, ['image_url' => 'https://example.com/old.png', 'visual_suggestion' => 'un gatto']);

    $this->mock(ImageGeneratorInterface::class, function ($mock) {
        $mock->shouldReceive('generateImage')->once()->andReturn('https://example.com/new.png');
    });

    $response = $this->actingAs($this->user)->postJson("/api/posts/{$post->id}/generate-image");

    $response->assertOk();
    expect($this->service->isWalletEnrolled($this->org->id))->toBeFalse();
});

it('generate-carousel: debits only on regeneration, same as generate-image', function () {
    $this->service->credit($this->org->id, 5, 'purchase');
    $post = createPost($this->project, ['carousel_images' => ['https://example.com/old.png']]);

    $this->mock(ImageGeneratorInterface::class, function ($mock) {
        $mock->shouldReceive('generateCarouselImages')->once()->andReturn(['images' => ['https://example.com/new.png']]);
    });

    $response = $this->actingAs($this->user)->postJson("/api/posts/{$post->id}/generate-carousel", [
        'visual_suggestion' => 'un gatto',
    ]);

    $response->assertOk();
    expect($this->service->balance($this->org->id))->toBe(4);
});
