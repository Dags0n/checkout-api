<?php

declare(strict_types=1);

namespace Payment\Domain\ValueObjects;

final readonly class WebhookSignatureContext
{
    public function __construct(
        public string $rawBody,
        public int $timestamp,
        public string $signature,
        public ?string $dataId = null,
        public ?string $requestId = null,
    ) {}
}
