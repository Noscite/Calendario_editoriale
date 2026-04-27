<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Eccezione per errori di logica applicativa noti.
 *
 * Pattern d'uso nei controller:
 *   try { ... } catch (SpecificException $e) {
 *       report($e);  // invia l'originale a Sentry
 *       throw new BusinessException('Messaggio sicuro per il cliente', 'ERROR_CODE', 500, $e);
 *   }
 *
 * Il global handler in bootstrap/app.php converte questa eccezione
 * in un JSON uniforme senza esporre stack trace.
 */
final class BusinessException extends RuntimeException
{
    public function __construct(
        public readonly string $publicMessage,
        public readonly string $errorCode,
        public readonly int $httpStatus = 400,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($publicMessage, 0, $previous);
    }
}
