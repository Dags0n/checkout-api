<?php

declare(strict_types=1);

namespace Payment\Infrastructure\Gateways\MercadoPago;

use Payment\Domain\Contracts\WebhookSignatureVerifierContract;
use Payment\Domain\ValueObjects\WebhookSignatureContext;

final class MercadoPagoSignatureVerifier implements WebhookSignatureVerifierContract
{
    public function verify(WebhookSignatureContext $context): bool
    {
        $tolerance = (int) config('payment.webhook.tolerance_seconds', 300);
        if (abs(time() - $context->timestamp) > $tolerance) {
            return false;
        }

        $secret = (string) config('payment.gateways.mercadopago.webhook_secret');
        if ($secret === '' || $context->dataId === null || $context->requestId === null) {
            return false;
        }

        $manifest = sprintf(
            'id:%s;request-id:%s;ts:%d;',
            strtolower($context->dataId),
            $context->requestId,
            $context->timestamp,
        );

        $expected = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($expected, $context->signature);
    }
}
