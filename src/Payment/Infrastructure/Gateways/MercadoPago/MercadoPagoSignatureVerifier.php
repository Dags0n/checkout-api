<?php

declare(strict_types=1);

namespace Payment\Infrastructure\Gateways\MercadoPago;

use Payment\Domain\Contracts\WebhookSignatureVerifierContract;

final class MercadoPagoSignatureVerifier implements WebhookSignatureVerifierContract
{
    public function verify(string $payload, string $signature, int $timestamp): bool
    {
        $tolerance = (int) config('payment.webhook.tolerance_seconds', 300);
        if (abs(time() - $timestamp) > $tolerance) {
            return false;
        }

        $secret = (string) config('payment.webhook.secret');
        if ($secret === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

        return hash_equals($expected, $signature);
    }
}
