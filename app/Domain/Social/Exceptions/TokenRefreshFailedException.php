<?php

declare(strict_types=1);

namespace App\Domain\Social\Exceptions;

use RuntimeException;

/**
 * Eccezione lanciata quando il refresh del token OAuth fallisce.
 * La connessione viene marcata come 'expired' prima del lancio.
 */
final class TokenRefreshFailedException extends RuntimeException
{
    public function __construct(string $platform, string $reason = '')
    {
        parent::__construct(
            "Refresh token fallito per {$platform}" . ($reason ? ": {$reason}" : '')
        );
    }
}
