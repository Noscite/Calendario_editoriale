<?php

declare(strict_types=1);

namespace App\Domain\Generation\Data;

/**
 * Parametri effettivi (già risolti) per una chiamata AI di un dato step:
 * brand override → default globale → costante hardcoded del codice.
 */
final class AiGenerationParams
{
    public function __construct(
        public readonly string $model,
        public readonly int    $maxTokens,
        public readonly ?float $temperature = null,
        public readonly ?float $topP = null,
        public readonly ?int   $topK = null,
        public readonly bool   $promptCachingEnabled = false,
    ) {}
}
