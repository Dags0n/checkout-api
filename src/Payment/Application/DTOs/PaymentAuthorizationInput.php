<?php

declare(strict_types=1);

namespace Payment\Application\DTOs;

use Payment\Domain\ValueObjects\CreditCard;

final readonly class PaymentAuthorizationInput
{
    public function __construct(
        public string $orderId,
        public int $amountCents,
        public string $currency,
        public CreditCard $creditCard,
        public string $customerName,
        public string $customerEmail,
    ) {}
}
