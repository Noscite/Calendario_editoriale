<?php

declare(strict_types=1);

namespace App\Domain\Generation\Contracts;

use App\Domain\Post\Models\Post;

interface ImageGeneratorInterface
{
    /**
     * Genera un'immagine per un post tramite DALL-E.
     *
     * @return string URL dell'immagine generata.
     */
    public function generateImage(int $postId, ?string $visualSuggestion = null): string;

    /**
     * Genera un prompt immagine ottimizzato per DALL-E da un post.
     */
    public function generateImagePrompt(int $postId): string;

    /**
     * Genera immagini carousel multi-slide per un post.
     *
     * @param  array{
     *     visual_suggestion?: string,
     *     image_format?: string,
     *     is_carousel?: bool,
     *     num_slides?: int,
     * }  $params
     * @return array{
     *     images: array<string>,
     *     prompts: array<string>,
     *     image_format: string,
     *     is_carousel: bool,
     * }
     */
    public function generateCarouselImages(int $postId, array $params): array;
}
