<?php

declare(strict_types=1);

namespace Order\Domain\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';

    public function isFinal(): bool
    {
        return $this === self::Paid || $this === self::Failed;
    }
}
