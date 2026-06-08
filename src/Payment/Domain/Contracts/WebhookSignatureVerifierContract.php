<?php

declare(strict_types=1);

namespace Payment\Domain\Contracts;

use Payment\Domain\ValueObjects\WebhookSignatureContext;

interface WebhookSignatureVerifierContract
{
    public function verify(WebhookSignatureContext $context): bool;
}
