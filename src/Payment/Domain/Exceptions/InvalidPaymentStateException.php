<?php

declare(strict_types=1);

namespace Payment\Domain\Exceptions;

use Shared\Domain\Exceptions\DomainException;

final class InvalidPaymentStateException extends DomainException
{
    public function __construct(
        public readonly string $paymentId,
        public readonly string $currentStatus,
        public readonly string $attemptedTransition,
    ) {
        parent::__construct(
            sprintf(
                'Payment %s is in status "%s" and cannot transition to "%s".',
                $paymentId,
                $currentStatus,
                $attemptedTransition,
            ),
            'INVALID_PAYMENT_STATE',
        );
    }

    public static function for(string $paymentId, string $currentStatus, string $attempted): self
    {
        return new self($paymentId, $currentStatus, $attempted);
    }
}
