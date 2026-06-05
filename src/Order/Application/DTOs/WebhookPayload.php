<?php

declare(strict_types=1);

namespace Order\Application\DTOs;

final readonly class WebhookPayload
{
    public function __construct(
        public string $transactionId,
        public string $status,
        public string $gateway,
    ) {}
}
