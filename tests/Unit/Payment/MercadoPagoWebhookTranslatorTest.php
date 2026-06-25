<?php

declare(strict_types=1);

namespace Tests\Unit\Payment;

use Illuminate\Support\Facades\Http;
use Payment\Infrastructure\Gateways\MercadoPago\MercadoPagoClient;
use Payment\Infrastructure\Gateways\MercadoPago\MercadoPagoWebhookTranslator;
use Shared\Domain\Exceptions\DomainException;
use Tests\TestCase;

final class MercadoPagoWebhookTranslatorTest extends TestCase
{
    private const API = 'https://api.mercadopago.com';

    public function test_it_fetches_status_and_normalizes_an_approved_payment(): void
    {
        $this->fakePayment('123456', 'approved');

        $payload = $this->translator()->translate(
            body: ['type' => 'payment', 'data' => ['id' => '123456']],
            query: [],
        );

        $this->assertNotNull($payload);
        $this->assertSame('123456', $payload->transactionId);
        $this->assertSame('approved', $payload->status);
        $this->assertSame('mercadopago', $payload->gateway);
    }

    public function test_it_normalizes_a_rejected_payment_to_declined(): void
    {
        $this->fakePayment('123456', 'rejected');

        $payload = $this->translator()->translate(
            body: ['data' => ['id' => '123456']],
            query: [],
        );

        $this->assertSame('declined', $payload?->status);
    }

    public function test_it_returns_null_for_a_pending_payment(): void
    {
        $this->fakePayment('123456', 'in_process');

        $payload = $this->translator()->translate(
            body: ['data' => ['id' => '123456']],
            query: [],
        );

        $this->assertNull($payload);
    }

    public function test_it_reads_the_id_from_the_query_when_body_is_empty(): void
    {
        $this->fakePayment('999', 'approved');

        $payload = $this->translator()->translate(
            body: [],
            query: ['data_id' => '999'],
        );

        $this->assertSame('999', $payload?->transactionId);
    }

    public function test_it_throws_when_no_payment_id_is_present(): void
    {
        Http::fake();

        $this->expectException(DomainException::class);

        $this->translator()->translate(body: [], query: []);
    }

    private function translator(): MercadoPagoWebhookTranslator
    {
        return new MercadoPagoWebhookTranslator(
            new MercadoPagoClient(self::API, 'test-token'),
        );
    }

    private function fakePayment(string $id, string $status): void
    {
        Http::fake([
            self::API . "/v1/payments/{$id}" => Http::response([
                'id' => (int) $id,
                'status' => $status,
            ], 200),
        ]);
    }
}
