<?php

namespace App\Domain\Post\Enums;

enum ContentType: string
{
    case Post = 'post';
    case Story = 'story';
    case Reel = 'reel';
    case Carousel = 'carousel';

    public function label(): string
    {
        return match ($this) {
            self::Post => 'Post',
            self::Story => 'Story',
            self::Reel => 'Reel',
            self::Carousel => 'Carosello',
        };
    }
}
