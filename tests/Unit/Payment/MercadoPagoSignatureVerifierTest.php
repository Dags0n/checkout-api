<?php

declare(strict_types=1);

namespace Tests\Unit\Payment;

use Payment\Domain\ValueObjects\WebhookSignatureContext;
use Payment\Infrastructure\Gateways\MercadoPago\MercadoPagoSignatureVerifier;
use Tests\TestCase;

final class MercadoPagoSignatureVerifierTest extends TestCase
{
    private const SECRET = 'mp-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payment.gateways.mercadopago.webhook_secret' => self::SECRET,
            'payment.webhook.tolerance_seconds' => 300,
        ]);
    }

    public function test_it_accepts_a_valid_manifest_signature(): void
    {
        $ts = time();
        $context = $this->context(
            dataId: '123456',
            requestId: 'req-1',
            timestamp: $ts,
            signature: $this->sign('123456', 'req-1', $ts),
        );

        $this->assertTrue((new MercadoPagoSignatureVerifier)->verify($context));
    }

    public function test_it_rejects_a_tampered_signature(): void
    {
        $ts = time();
        $context = $this->context(
            dataId: '123456',
            requestId: 'req-1',
            timestamp: $ts,
            signature: str_repeat('0', 64),
        );

        $this->assertFalse((new MercadoPagoSignatureVerifier)->verify($context));
    }

    public function test_it_rejects_an_expired_timestamp(): void
    {
        $ts = time() - 600;
        $context = $this->context(
            dataId: '123456',
            requestId: 'req-1',
            timestamp: $ts,
            signature: $this->sign('123456', 'req-1', $ts),
        );

        $this->assertFalse((new MercadoPagoSignatureVerifier)->verify($context));
    }

    public function test_it_rejects_when_data_id_is_missing(): void
    {
        $ts = time();
        $context = $this->context(
            dataId: null,
            requestId: 'req-1',
            timestamp: $ts,
            signature: $this->sign('123456', 'req-1', $ts),
        );

        $this->assertFalse((new MercadoPagoSignatureVerifier)->verify($context));
    }

    private function context(?string $dataId, ?string $requestId, int $timestamp, string $signature): WebhookSignatureContext
    {
        return new WebhookSignatureContext(
            rawBody: '{"type":"payment","data":{"id":"123456"}}',
            timestamp: $timestamp,
            signature: $signature,
            dataId: $dataId,
            requestId: $requestId,
        );
    }

    private function sign(string $dataId, string $requestId, int $timestamp): string
    {
        $manifest = sprintf('id:%s;request-id:%s;ts:%d;', strtolower($dataId), $requestId, $timestamp);

        return hash_hmac('sha256', $manifest, self::SECRET);
    }
}
