<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Review\Enums\ReviewStatus;
use App\Domain\Review\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
final class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        $rating = fake()->numberBetween(1, 5);

        return [
            // organization_id / social_connection_id / brand_id devono essere settati
            // dal test che usa la factory (legati alla connection di Google).
            'platform'           => 'google_business',
            'external_review_id' => 'rev-' . fake()->unique()->uuid(),
            'reviewer_name'      => fake()->boolean(80) ? fake()->name() : 'A Google User',
            'reviewer_photo_url' => fake()->boolean(60) ? fake()->imageUrl(80, 80, 'people') : null,
            'rating'             => $rating,
            'comment'            => fake()->boolean(70) ? fake()->paragraph() : null,
            'language'           => 'it',
            'review_created_at'  => fake()->dateTimeBetween('-30 days'),
            'review_updated_at'  => null,
            'fetched_at'         => now(),
            'status'             => ReviewStatus::New->value,
            'raw_payload'        => ['stub' => true, 'rating' => $rating],
        ];
    }
}
