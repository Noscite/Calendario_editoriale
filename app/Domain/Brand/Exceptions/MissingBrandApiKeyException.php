<?php

declare(strict_types=1);

namespace App\Domain\Brand\Exceptions;

use RuntimeException;

class MissingBrandApiKeyException extends RuntimeException
{
    public function __construct(
        public readonly string $keyName,
        public readonly string $brandName,
    ) {
        parent::__construct(
            "Il brand \"{$brandName}\" non ha configurato la chiave API richiesta: {$keyName}. " .
            "Vai in Dettaglio Brand → Integrazioni & API Keys per inserirla."
        );
    }
}
