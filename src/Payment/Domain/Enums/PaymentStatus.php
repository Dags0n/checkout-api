<?php

declare(strict_types=1);

namespace Payment\Domain\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';

    public function isFinal(): bool
    {
        return $this === self::Approved || $this === self::Declined;
    }
}
