<?php

declare(strict_types=1);

namespace Tests\Unit\Payment;

use Payment\Domain\Enums\PaymentStatus;
use Payment\Domain\Exceptions\InvalidPaymentStateException;
use Payment\Domain\Payment;
use PHPUnit\Framework\TestCase;

final class PaymentTest extends TestCase
{
    public function test_mark_pending_sets_transaction_id_and_status(): void
    {
        $payment = $this->freshPayment();
        $payment->markPending('fake_abc-123', '1111');

        $this->assertSame('fake_abc-123', $payment->transaction_id);
        $this->assertSame('1111', $payment->card_last4);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
    }

    public function test_mark_approved_from_pending(): void
    {
        $payment = $this->freshPayment();
        $payment->markPending('fake_abc', '1111');
        $payment->markApproved();

        $this->assertSame(PaymentStatus::Approved, $payment->status);
    }

    public function test_mark_declined_from_pending(): void
    {
        $payment = $this->freshPayment();
        $payment->markPending('fake_abc', '1111');
        $payment->markDeclined();

        $this->assertSame(PaymentStatus::Declined, $payment->status);
    }

    public function test_it_rejects_double_mark_approved(): void
    {
        $payment = $this->freshPayment();
        $payment->markPending('fake_abc', '1111');
        $payment->markApproved();

        $this->expectException(InvalidPaymentStateException::class);

        $payment->markApproved();
    }

    public function test_it_rejects_mark_pending_when_already_set(): void
    {
        $payment = $this->freshPayment();
        $payment->markPending('fake_abc', '1111');

        $this->expectException(InvalidPaymentStateException::class);

        $payment->markPending('fake_xyz', '2222');
    }

    public function test_it_rejects_pending_to_approved_via_approved(): void
    {
        $payment = $this->freshPayment();
        $payment->markPending('fake_abc', '1111');
        $payment->markApproved();

        $this->expectException(InvalidPaymentStateException::class);
        $this->expectExceptionMessage('cannot transition to "approved"');

        $payment->markApproved();
    }

    private function freshPayment(): Payment
    {
        $payment = new Payment;
        $payment->order_id = 'ord-1';
        $payment->gateway = 'fake';
        $payment->amount_cents = 10000;
        $payment->currency = 'BRL';

        return $payment;
    }
}
