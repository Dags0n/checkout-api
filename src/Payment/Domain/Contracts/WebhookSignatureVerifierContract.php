<?php

declare(strict_types=1);

namespace Payment\Domain\Contracts;

interface WebhookSignatureVerifierContract
{
    public function verify(string $payload, string $signature, int $timestamp): bool;
}
