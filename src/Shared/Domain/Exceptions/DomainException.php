<?php

declare(strict_types=1);

namespace Shared\Domain\Exceptions;

use RuntimeException;
use Throwable;

class DomainException extends RuntimeException
{
    public function __construct(
        string $message = '',
        public readonly string $errorCode = 'DOMAIN_ERROR',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
