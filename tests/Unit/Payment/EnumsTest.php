<?php

declare(strict_types=1);

namespace Tests\Unit\Payment;

use Order\Domain\Enums\OrderStatus;
use Payment\Domain\Enums\PaymentStatus;
use PHPUnit\Framework\TestCase;

final class EnumsTest extends TestCase
{
    public function test_payment_status_is_final(): void
    {
        $this->assertFalse(PaymentStatus::Pending->isFinal());
        $this->assertTrue(PaymentStatus::Approved->isFinal());
        $this->assertTrue(PaymentStatus::Declined->isFinal());
    }

    public function test_order_status_is_final(): void
    {
        $this->assertFalse(OrderStatus::Pending->isFinal());
        $this->assertTrue(OrderStatus::Paid->isFinal());
        $this->assertTrue(OrderStatus::Failed->isFinal());
    }

    public function test_status_values_match_db_check_constraints(): void
    {
        $this->assertSame('pending', PaymentStatus::Pending->value);
        $this->assertSame('approved', PaymentStatus::Approved->value);
        $this->assertSame('declined', PaymentStatus::Declined->value);

        $this->assertSame('pending', OrderStatus::Pending->value);
        $this->assertSame('paid', OrderStatus::Paid->value);
        $this->assertSame('failed', OrderStatus::Failed->value);
    }
}
