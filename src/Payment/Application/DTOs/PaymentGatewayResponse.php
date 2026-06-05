<?php

declare(strict_types=1);

namespace Payment\Application\DTOs;

use Payment\Domain\Enums\PaymentStatus;

final readonly class PaymentGatewayResponse
{
    public function __construct(
        public string $transactionId,
        public PaymentStatus $status,
        public array $metadata = [],
    ) {}
}
