<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Catalog\Domain\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Order\Domain\Enums\OrderStatus;
use Order\Domain\Order;
use Payment\Domain\Enums\PaymentStatus;
use Payment\Domain\Payment;
use Tests\TestCase;

final class SimulatePaymentWebhookCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_even_last_digit_approves_order(): void
    {
        $order = $this->makeOrderWithPayment(cardLast4: '1112', status: PaymentStatus::Pending);

        $this->artisan('app:simulate-payment-webhook')->assertSuccessful();

        $this->assertSame(PaymentStatus::Approved, $order->latestPayment()->fresh()->status);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_odd_last_digit_declines_order(): void
    {
        $order = $this->makeOrderWithPayment(cardLast4: '1117', status: PaymentStatus::Pending);

        $this->artisan('app:simulate-payment-webhook')->assertSuccessful();

        $this->assertSame(PaymentStatus::Declined, $order->latestPayment()->fresh()->status);
        $this->assertSame(OrderStatus::Failed, $order->fresh()->status);
    }

    public function test_finalized_payments_are_skipped(): void
    {
        $order = $this->makeOrderWithPayment(
            cardLast4: '1112',
            status: PaymentStatus::Approved,
        );

        $stockBefore = (int) Product::first()->stock;
        $this->artisan('app:simulate-payment-webhook')->assertSuccessful();
        $stockAfter = (int) Product::first()->stock;

        $this->assertSame($stockBefore, $stockAfter);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_no_pending_orders_is_a_clean_no_op(): void
    {
        $this->artisan('app:simulate-payment-webhook')
            ->expectsOutputToContain('Found 0 pending order(s)')
            ->assertSuccessful();
    }

    private function makeOrderWithPayment(string $cardLast4, PaymentStatus $status): Order
    {
        $product = Product::factory()->create(['price_cents' => 5000, 'stock' => 10]);

        $orderStatus = match ($status) {
            PaymentStatus::Approved => OrderStatus::Paid,
            PaymentStatus::Declined => OrderStatus::Failed,
            default => OrderStatus::Pending,
        };

        $order = Order::factory()->create(['status' => $orderStatus]);
        $order->addItem($product, 1);

        Payment::factory()->create([
            'order_id' => $order->id,
            'amount_cents' => (int) $order->total_cents,
            'status' => $status,
            'card_last4' => $cardLast4,
        ]);

        return $order->refresh();
    }
}
