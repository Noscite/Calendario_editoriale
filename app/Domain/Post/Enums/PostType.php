<?php

namespace App\Domain\Post\Enums;

enum PostType: string
{
    case Educational = 'educational';
    case Engagement = 'engagement';
    case ProductDemo = 'product_demo';
    case BehindTheScenes = 'behind_the_scenes';
    case UserGenerated = 'user_generated';
    case Promotional = 'promotional';
    case ThoughtLeadership = 'thought_leadership';

    public function label(): string
    {
        return match ($this) {
            self::Educational => 'Educativo',
            self::Engagement => 'Engagement',
            self::ProductDemo => 'Demo Prodotto',
            self::BehindTheScenes => 'Dietro le Quinte',
            self::UserGenerated => 'User Generated',
            self::Promotional => 'Promozionale',
            self::ThoughtLeadership => 'Thought Leadership',
        };
    }
}
