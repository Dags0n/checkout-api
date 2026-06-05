<?php

declare(strict_types=1);

namespace Order\Domain\Exceptions;

use Shared\Domain\Exceptions\DomainException;

final class InvalidOrderStateException extends DomainException
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $currentStatus,
        public readonly string $attemptedTransition,
    ) {
        parent::__construct(
            sprintf(
                'Order %s is in status "%s" and cannot transition to "%s".',
                $orderId,
                $currentStatus,
                $attemptedTransition,
            ),
            'INVALID_ORDER_STATE',
        );
    }
}
