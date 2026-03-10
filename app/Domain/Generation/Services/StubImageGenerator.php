<?php

declare(strict_types=1);

namespace App\Domain\Generation\Services;

use App\Domain\Generation\Contracts\ImageGeneratorInterface;

/**
 * Implementazione stub del generatore di immagini.
 *
 * Verrà sostituita con DalleImageGenerator quando DALL-E/OpenAI sarà configurato.
 */
final class StubImageGenerator implements ImageGeneratorInterface
{
    public function generateImage(int $postId, ?string $visualSuggestion = null): string
    {
        return '';
    }

    public function generateImagePrompt(int $postId): string
    {
        return '';
    }

    public function generateCarouselImages(int $postId, array $params): array
    {
        return [
            'images'       => [],
            'prompts'      => [],
            'image_format' => $params['image_format'] ?? '1080x1080',
            'is_carousel'  => $params['is_carousel'] ?? false,
        ];
    }
}
