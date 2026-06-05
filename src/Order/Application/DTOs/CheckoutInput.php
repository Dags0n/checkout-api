<?php

declare(strict_types=1);

namespace Order\Application\DTOs;

use Payment\Domain\ValueObjects\CreditCard;

final readonly class CheckoutInput
{
    public function __construct(
        public string $customerName,
        public string $customerEmail,
        public CreditCard $creditCard,
        public array $items,
    ) {}
}
