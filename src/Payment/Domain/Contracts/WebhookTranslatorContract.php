<?php

declare(strict_types=1);

namespace Payment\Domain\Contracts;

use Order\Application\DTOs\WebhookPayload;

interface WebhookTranslatorContract
{
    public function translate(array $body, array $query): ?WebhookPayload;
}
