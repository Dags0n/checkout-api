<?php

declare(strict_types=1);

namespace Tests\Unit\Payment;

use Payment\Application\DTOs\PaymentAuthorizationInput;
use Payment\Domain\Enums\PaymentStatus;
use Payment\Domain\ValueObjects\CreditCard;
use Payment\Infrastructure\Gateways\Fake\FakePaymentGateway;
use PHPUnit\Framework\TestCase;

final class FakePaymentGatewayTest extends TestCase
{
    public function test_it_returns_pending_status_for_every_card(): void
    {
        $gateway = new FakePaymentGateway;

        $even = $gateway->charge($this->input('4111111111111112'));
        $odd = $gateway->charge($this->input('4111111111111117'));

        $this->assertSame(PaymentStatus::Pending, $even->status);
        $this->assertSame(PaymentStatus::Pending, $odd->status);
    }

    public function test_it_returns_a_fake_prefixed_transaction_id(): void
    {
        $gateway = new FakePaymentGateway;

        $response = $gateway->charge($this->input('4111111111111111'));

        $this->assertStringStartsWith('fake_', $response->transactionId);
        $this->assertSame(36 + 5, strlen($response->transactionId));
    }

    public function test_it_emits_metadata_with_card_last4(): void
    {
        $gateway = new FakePaymentGateway;

        $response = $gateway->charge($this->input('4111111111111111'));

        $this->assertSame('1111', $response->metadata['card_last4']);
        $this->assertSame(10000, $response->metadata['amount_cents']);
    }

    public function test_it_simulates_latency_of_at_least_one_second(): void
    {
        $gateway = new FakePaymentGateway;

        $start = microtime(true);
        $gateway->charge($this->input('4111111111111111'));
        $elapsed = microtime(true) - $start;

        $this->assertGreaterThanOrEqual(1.0, $elapsed);
    }

    private function input(string $number): PaymentAuthorizationInput
    {
        return new PaymentAuthorizationInput(
            orderId: 'ord-1',
            amountCents: 10000,
            currency: 'BRL',
            creditCard: new CreditCard($number, 'TEST', '12/27', '123'),
            customerName: 'Test',
            customerEmail: 'test@example.com',
        );
    }
}
