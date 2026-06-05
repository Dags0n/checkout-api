<?php

declare(strict_types=1);

namespace Tests\Unit\Payment;

use Catalog\Domain\Contracts\ProductRepositoryContract;
use Catalog\Domain\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Order\Application\DTOs\WebhookPayload;
use Order\Domain\Enums\OrderStatus;
use Order\Domain\Exceptions\OutOfStockException;
use Order\Domain\Order;
use Payment\Application\Services\PaymentWebhookService;
use Payment\Domain\Contracts\PaymentRepositoryContract;
use Payment\Domain\Enums\PaymentStatus;
use Payment\Domain\Payment;
use Shared\Domain\Exceptions\DomainException;
use Tests\TestCase;

final class PaymentWebhookServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_webhook_marks_payment_approved_and_order_paid(): void
    {
        $order = $this->makeOrderWithItem(priceCents: 5000, quantity: 2, stock: 10);
        $payment = $this->makePendingPayment($order, 'fake_tx_1');

        $this->makeService()->process(new WebhookPayload(
            transactionId: 'fake_tx_1',
            status: 'approved',
            gateway: 'fake',
        ));

        $this->assertSame(PaymentStatus::Approved, $payment->fresh()->status);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_approved_webhook_decrements_stock(): void
    {
        $product = Product::factory()->create(['price_cents' => 5000, 'stock' => 10]);
        $order = $this->makeOrderWithItem($product, quantity: 3);
        $this->makePendingPayment($order, 'fake_tx_2');

        $this->makeService()->process(new WebhookPayload(
            transactionId: 'fake_tx_2',
            status: 'approved',
            gateway: 'fake',
        ));

        $this->assertSame(7, (int) $product->fresh()->stock);
    }

    public function test_declined_webhook_does_not_decrement_stock(): void
    {
        $product = Product::factory()->create(['price_cents' => 5000, 'stock' => 10]);
        $order = $this->makeOrderWithItem($product, quantity: 3);
        $this->makePendingPayment($order, 'fake_tx_3');

        $this->makeService()->process(new WebhookPayload(
            transactionId: 'fake_tx_3',
            status: 'declined',
            gateway: 'fake',
        ));

        $this->assertSame(10, (int) $product->fresh()->stock);
        $this->assertSame(PaymentStatus::Declined, $order->latestPayment()->fresh()->status);
        $this->assertSame(OrderStatus::Failed, $order->fresh()->status);
    }

    public function test_duplicate_webhook_is_idempotent_via_transaction_id(): void
    {
        $order = $this->makeOrderWithItem(priceCents: 5000, quantity: 2, stock: 10);
        $this->makePendingPayment($order, 'fake_tx_4');

        $service = $this->makeService();

        $service->process(new WebhookPayload('fake_tx_4', 'approved', 'fake'));
        $firstStock = (int) Product::first()->stock;

        $service->process(new WebhookPayload('fake_tx_4', 'approved', 'fake'));
        $secondStock = (int) Product::first()->stock;

        $this->assertSame($firstStock, $secondStock);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_webhook_for_already_finalized_order_is_idempotent(): void
    {
        $order = $this->makeOrderWithItem(priceCents: 5000, quantity: 2, stock: 10);
        $order->update(['status' => OrderStatus::Paid]);
        $payment = $this->makePendingPayment($order, 'fake_tx_5');

        $this->makeService()->process(new WebhookPayload(
            transactionId: 'fake_tx_5',
            status: 'declined',
            gateway: 'fake',
        ));

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_webhook_for_unknown_transaction_throws_domain_exception(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('No payment found for transaction_id "missing"');

        $this->makeService()->process(new WebhookPayload(
            transactionId: 'missing',
            status: 'approved',
            gateway: 'fake',
        ));
    }

    public function test_webhook_with_invalid_status_throws_domain_exception(): void
    {
        $order = $this->makeOrderWithItem(priceCents: 5000, quantity: 1, stock: 10);
        $this->makePendingPayment($order, 'fake_tx_6');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unknown webhook status');

        $this->makeService()->process(new WebhookPayload(
            transactionId: 'fake_tx_6',
            status: 'frozen',
            gateway: 'fake',
        ));
    }

    public function test_concurrent_approvals_against_limited_stock_honor_lock(): void
    {
        $product = Product::factory()->create(['price_cents' => 5000, 'stock' => 5]);

        $orderA = $this->makeOrderWithItem($product, quantity: 5);
        $orderB = $this->makeOrderWithItem($product, quantity: 5);

        $this->makePendingPayment($orderA, 'fake_tx_A');
        $this->makePendingPayment($orderB, 'fake_tx_B');

        $service = $this->makeService();

        $service->process(new WebhookPayload('fake_tx_A', 'approved', 'fake'));
        $this->assertSame(0, (int) $product->fresh()->stock);
        $this->assertSame(OrderStatus::Paid, $orderA->fresh()->status);

        $threw = false;
        try {
            $service->process(new WebhookPayload('fake_tx_B', 'approved', 'fake'));
        } catch (OutOfStockException $e) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Expected OutOfStockException for oversold scenario.');

        $this->assertSame(OrderStatus::Pending, $orderB->fresh()->status);
    }

    private function makeService(): PaymentWebhookService
    {
        return new PaymentWebhookService(
            $this->app->make(PaymentRepositoryContract::class),
            $this->app->make(ProductRepositoryContract::class),
        );
    }

    private function makeOrderWithItem(
        ?Product $product = null,
        int $priceCents = 5000,
        int $quantity = 1,
        int $stock = 10,
    ): Order {
        $product ??= Product::factory()->create([
            'price_cents' => $priceCents,
            'stock' => $stock,
        ]);

        $order = Order::factory()->create();
        $order->addItem($product, $quantity);

        return $order->refresh();
    }

    private function makePendingPayment(Order $order, string $transactionId): Payment
    {
        return Payment::factory()->create([
            'order_id' => $order->id,
            'transaction_id' => $transactionId,
            'amount_cents' => (int) $order->total_cents,
            'status' => PaymentStatus::Pending,
        ]);
    }
}
