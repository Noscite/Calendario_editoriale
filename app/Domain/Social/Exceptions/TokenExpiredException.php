<?php

declare(strict_types=1);

namespace App\Domain\Social\Exceptions;

use RuntimeException;

/**
 * Eccezione lanciata quando il token OAuth risulta scaduto (HTTP 401).
 * Il chiamante deve invocare il refresh del token e riprovare.
 */
final class TokenExpiredException extends RuntimeException
{
    public function __construct(string $platform, string $message = '')
    {
        parent::__construct(
            $message ?: "Token scaduto per la piattaforma: {$platform}"
        );
    }
}
