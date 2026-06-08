<?php

declare(strict_types=1);

namespace Payment\Infrastructure\Gateways\Fake;

use Payment\Domain\Contracts\WebhookSignatureVerifierContract;
use Payment\Domain\ValueObjects\WebhookSignatureContext;

final class FakeSignatureVerifier implements WebhookSignatureVerifierContract
{
    public function verify(WebhookSignatureContext $context): bool
    {
        $tolerance = (int) config('payment.webhook.tolerance_seconds', 300);
        if (abs(time() - $context->timestamp) > $tolerance) {
            return false;
        }

        $secret = (string) config('payment.webhook.secret');
        if ($secret === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $context->timestamp.'.'.$context->rawBody, $secret);

        return hash_equals($expected, $context->signature);
    }
}
