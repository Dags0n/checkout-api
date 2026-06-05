<?php

declare(strict_types=1);

namespace Payment\Domain\ValueObjects;

use InvalidArgumentException;
use Stringable;

final readonly class TransactionId implements Stringable
{
    public function __construct(public string $value)
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 128) {
            throw new InvalidArgumentException('Transaction id must be 1-128 characters.');
        }
        $this->value = $value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
