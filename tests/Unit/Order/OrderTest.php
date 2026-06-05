<?php

declare(strict_types=1);

namespace Tests\Unit\Order;

use Catalog\Domain\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Order\Domain\Enums\OrderStatus;
use Order\Domain\Exceptions\InvalidOrderStateException;
use Order\Domain\Order;
use Tests\TestCase;

final class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_calculates_total_from_items(): void
    {
        $order = Order::factory()->create();
        $shirt = Product::factory()->create(['price_cents' => 5000, 'stock' => 10]);
        $shorts = Product::factory()->create(['price_cents' => 8000, 'stock' => 10]);

        $order->addItem($shirt, 2);   // subtotal 10000
        $order->addItem($shorts, 1);  // subtotal 8000

        $this->assertSame(18000, (int) $order->fresh()->total_cents);
        $this->assertCount(2, $order->fresh()->items);
    }

    public function test_it_reports_is_finalized_correctly(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);
        $this->assertFalse($order->isFinalized());

        $order->status = OrderStatus::Paid;
        $this->assertTrue($order->isFinalized());

        $order->status = OrderStatus::Failed;
        $this->assertTrue($order->isFinalized());
    }

    public function test_it_transitions_from_pending_to_paid(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);
        $order->confirmPayment();
        $order->save();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_it_transitions_from_pending_to_failed(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);
        $order->failPayment();
        $order->save();

        $this->assertSame(OrderStatus::Failed, $order->fresh()->status);
    }

    public function test_it_rejects_double_confirmation(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Paid]);

        $this->expectException(InvalidOrderStateException::class);

        $order->confirmPayment();
    }

    public function test_it_rejects_paid_to_failed_transition(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Paid]);

        $this->expectException(InvalidOrderStateException::class);
        $this->expectExceptionMessage('cannot transition to "failed"');

        $order->failPayment();
    }

    public function test_invalid_state_exception_carries_metadata(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Failed]);

        try {
            $order->confirmPayment();
            $this->fail('Expected InvalidOrderStateException');
        } catch (InvalidOrderStateException $e) {
            $this->assertSame('failed', $e->currentStatus);
            $this->assertSame('paid', $e->attemptedTransition);
            $this->assertSame('INVALID_ORDER_STATE', $e->errorCode);
        }
    }

    public function test_add_item_rejects_zero_quantity(): void
    {
        $order = Order::factory()->create();
        $product = Product::factory()->create(['stock' => 10]);

        $this->expectException(\InvalidArgumentException::class);

        $order->addItem($product, 0);
    }
}
