<?php

declare(strict_types=1);

namespace Payment\Infrastructure\Gateways\Fake;

use Order\Application\DTOs\WebhookPayload;
use Payment\Domain\Contracts\WebhookTranslatorContract;
use Shared\Domain\Exceptions\DomainException;

final class FakeWebhookTranslator implements WebhookTranslatorContract
{
    public function translate(array $body, array $query): ?WebhookPayload
    {
        $transactionId = $body['transaction_id'] ?? null;
        $status = $body['status'] ?? null;
        $gateway = $body['gateway'] ?? null;

        if (! is_string($transactionId) || ! is_string($status) || ! is_string($gateway)) {
            throw new DomainException(
                'Malformed fake webhook payload.',
                'INVALID_WEBHOOK_PAYLOAD',
            );
        }

        if (! in_array($status, ['approved', 'declined'], true)) {
            throw new DomainException(
                sprintf('Unsupported fake webhook status "%s".', $status),
                'INVALID_WEBHOOK_STATUS',
            );
        }

        return new WebhookPayload(
            transactionId: $transactionId,
            status: $status,
            gateway: $gateway,
        );
    }
}
