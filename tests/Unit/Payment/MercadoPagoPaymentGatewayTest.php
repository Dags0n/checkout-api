<?php

declare(strict_types=1);

namespace Tests\Unit\Payment;

use Illuminate\Support\Facades\Http;
use Payment\Application\DTOs\PaymentAuthorizationInput;
use Payment\Domain\Enums\PaymentStatus;
use Payment\Domain\ValueObjects\CreditCard;
use Payment\Infrastructure\Gateways\MercadoPago\MercadoPagoClient;
use Payment\Infrastructure\Gateways\MercadoPago\MercadoPagoPaymentGateway;
use Shared\Domain\Exceptions\DomainException;
use Tests\TestCase;

final class MercadoPagoPaymentGatewayTest extends TestCase
{
    private const API = 'https://api.mercadopago.com';

    public function test_it_tokenizes_charges_and_maps_an_approved_payment(): void
    {
        $this->fakeMercadoPago(paymentStatus: 'approved');

        $response = $this->gateway()->charge($this->input(amountCents: 5000));

        $this->assertSame('123456', $response->transactionId);
        $this->assertSame(PaymentStatus::Approved, $response->status);
        $this->assertSame('master', $response->metadata['payment_method_id']);
    }

    public function test_it_maps_rejected_payment_to_declined(): void
    {
        $this->fakeMercadoPago(paymentStatus: 'rejected');

        $response = $this->gateway()->charge($this->input());

        $this->assertSame(PaymentStatus::Declined, $response->status);
    }

    public function test_it_maps_in_process_payment_to_pending(): void
    {
        $this->fakeMercadoPago(paymentStatus: 'in_process');

        $response = $this->gateway()->charge($this->input());

        $this->assertSame(PaymentStatus::Pending, $response->status);
    }

    public function test_it_sends_idempotency_key_and_decimal_amount(): void
    {
        $this->fakeMercadoPago(paymentStatus: 'approved');

        $this->gateway()->charge($this->input(orderId: 'ord-42', amountCents: 5000));

        Http::assertSent(fn($request): bool => str_contains($request->url(), '/v1/payments')
            && $request->hasHeader('X-Idempotency-Key', 'ord-42')
            && (float) $request['transaction_amount'] === 50.0
            && $request['token'] === 'tok_123'
            && $request['payment_method_id'] === 'master');
    }

    public function test_it_raises_a_domain_error_when_mercadopago_fails(): void
    {
        Http::fake([
            self::API . '/v1/card_tokens' => Http::response(['message' => 'bad card'], 400),
        ]);

        $this->expectException(DomainException::class);

        $this->gateway()->charge($this->input());
    }

    private function gateway(): MercadoPagoPaymentGateway
    {
        return new MercadoPagoPaymentGateway(
            new MercadoPagoClient(self::API, 'test-token'),
        );
    }

    private function fakeMercadoPago(string $paymentStatus): void
    {
        Http::fake([
            self::API . '/v1/card_tokens' => Http::response([
                'id' => 'tok_123',
                'first_six_digits' => '503143',
            ], 201),
            self::API . '/v1/payment_methods/search*' => Http::response([
                'results' => [['id' => 'master']],
            ], 200),
            self::API . '/v1/payments' => Http::response([
                'id' => 123456,
                'status' => $paymentStatus,
                'status_detail' => 'accredited',
                'payment_method_id' => 'master',
                'card' => ['last_four_digits' => '6351'],
                'installments' => 1,
            ], 201),
        ]);
    }

    private function input(string $orderId = 'ord-1', int $amountCents = 5000): PaymentAuthorizationInput
    {
        return new PaymentAuthorizationInput(
            orderId: $orderId,
            amountCents: $amountCents,
            currency: 'BRL',
            creditCard: new CreditCard('5031433215406351', 'APRO TEST', '11/30', '123'),
            customerName: 'APRO TEST',
            customerEmail: 'test@example.com',
        );
    }
}
