<?php

declare(strict_types=1);

namespace Order\Application\DTOs;

use Order\Domain\Order;
use Payment\Domain\Payment;

final readonly class CheckoutResult
{
    public function __construct(
        public Order $order,
        public Payment $payment,
    ) {}
}
