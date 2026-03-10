<?php

declare(strict_types=1);

namespace App\Domain\Post\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

/**
 * DTO per la creazione massiva di Post — corrisponde a BulkPostCreateInput (Python).
 */
final class BulkCreatePostData extends Data
{
    public function __construct(
        /** @var array<CreatePostData> */
        #[Required, DataCollectionOf(CreatePostData::class)]
        public readonly array $posts,
    ) {}
}
