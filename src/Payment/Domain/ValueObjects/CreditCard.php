<?php

declare(strict_types=1);

namespace Payment\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class CreditCard
{
    public string $number;

    public string $holderName;

    public string $expiry;

    public string $cvv;

    public function __construct(
        string $number,
        string $holderName,
        string $expiry,
        string $cvv,
    ) {
        $cleanNumber = preg_replace('/\s+/', '', $number);
        if (! is_string($cleanNumber) || ! ctype_digit($cleanNumber) || strlen($cleanNumber) < 13 || strlen($cleanNumber) > 19) {
            throw new InvalidArgumentException('Credit card number must be 13-19 digits.');
        }
        $this->number = $cleanNumber;

        $holder = trim($holderName);
        if ($holder === '' || mb_strlen($holder) > 255) {
            throw new InvalidArgumentException('Holder name is required and must be at most 255 characters.');
        }
        $this->holderName = $holder;

        if (! preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $expiry)) {
            throw new InvalidArgumentException('Expiry must be in MM/YY format.');
        }
        $this->expiry = $expiry;

        if (! ctype_digit($cvv) || strlen($cvv) < 3 || strlen($cvv) > 4) {
            throw new InvalidArgumentException('CVV must be 3 or 4 digits.');
        }
        $this->cvv = $cvv;
    }

    public function lastDigit(): int
    {
        return (int) substr($this->number, -1);
    }

    public function last4(): string
    {
        return substr($this->number, -4);
    }
}
