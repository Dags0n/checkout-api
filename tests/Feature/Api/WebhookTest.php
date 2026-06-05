<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Catalog\Domain\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Order\Domain\Enums\OrderStatus;
use Order\Domain\Order;
use Payment\Domain\Enums\PaymentStatus;
use Payment\Domain\Payment;
use Tests\TestCase;

final class WebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_webhook_approves_payment_and_order(): void
    {
        $order = $this->makeOrderWithItem(priceCents: 5000, quantity: 1, stock: 10);
        $payment = $this->makePendingPayment($order, 'fake_signed_1');

        $payload = [
            'transaction_id' => 'fake_signed_1',
            'status' => 'approved',
            'gateway' => 'fake',
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $response = $this->postJson('/api/v1/webhooks/payment', $payload, $this->signatureHeaders($body));

        $response->assertOk();
        $response->assertJsonPath('data.processed', true);
        $this->assertSame(PaymentStatus::Approved, $payment->fresh()->status);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_webhook_without_signature_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/webhooks/payment', [
            'transaction_id' => 'fake_signed_2',
            'status' => 'approved',
            'gateway' => 'fake',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('meta.code', 'INVALID_SIGNATURE');
    }

    public function test_webhook_with_invalid_signature_is_rejected(): void
    {
        $order = $this->makeOrderWithItem(priceCents: 5000, quantity: 1, stock: 10);
        $this->makePendingPayment($order, 'fake_signed_3');

        $payload = [
            'transaction_id' => 'fake_signed_3',
            'status' => 'approved',
            'gateway' => 'fake',
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $headers = $this->signatureHeaders($body);
        $headers['X-Signature'] = 'v1=' . str_repeat('0', 64);

        $response = $this->postJson('/api/v1/webhooks/payment', $payload, $headers);

        $response->assertStatus(401);
    }

    public function test_webhook_with_expired_timestamp_is_rejected(): void
    {
        $order = $this->makeOrderWithItem(priceCents: 5000, quantity: 1, stock: 10);
        $this->makePendingPayment($order, 'fake_signed_4');

        $payload = [
            'transaction_id' => 'fake_signed_4',
            'status' => 'approved',
            'gateway' => 'fake',
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $expiredTs = time() - 600; // past the 300s tolerance
        $headers = [
            'X-Signature-Timestamp' => (string) $expiredTs,
            'X-Signature' => 'v1=' . hash_hmac('sha256', $expiredTs . '.' . $body, config('payment.webhook.secret')),
        ];

        $response = $this->postJson('/api/v1/webhooks/payment', $payload, $headers);

        $response->assertStatus(401);
    }

    public function test_duplicate_webhook_is_idempotent(): void
    {
        $order = $this->makeOrderWithItem(priceCents: 5000, quantity: 1, stock: 10);
        $this->makePendingPayment($order, 'fake_signed_5');

        $payload = [
            'transaction_id' => 'fake_signed_5',
            'status' => 'approved',
            'gateway' => 'fake',
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $headers = $this->signatureHeaders($body);

        $this->postJson('/api/v1/webhooks/payment', $payload, $headers)->assertOk();
        $stockAfterFirst = (int) Product::first()->stock;

        $this->postJson('/api/v1/webhooks/payment', $payload, $headers)->assertOk();
        $stockAfterSecond = (int) Product::first()->stock;

        $this->assertSame($stockAfterFirst, $stockAfterSecond);
    }

    public function test_webhook_for_already_paid_order_does_not_change_state(): void
    {
        $order = $this->makeOrderWithItem(priceCents: 5000, quantity: 1, stock: 10);
        $order->update(['status' => OrderStatus::Paid]);
        $this->makePendingPayment($order, 'fake_signed_6');

        $payload = [
            'transaction_id' => 'fake_signed_6',
            'status' => 'declined',
            'gateway' => 'fake',
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->postJson('/api/v1/webhooks/payment', $payload, $this->signatureHeaders($body))->assertOk();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_declined_webhook_does_not_decrement_stock(): void
    {
        $product = Product::factory()->create(['price_cents' => 5000, 'stock' => 10]);
        $order = $this->makeOrderWithItem(quantity: 3, product: $product);
        $this->makePendingPayment($order, 'fake_signed_7');

        $payload = [
            'transaction_id' => 'fake_signed_7',
            'status' => 'declined',
            'gateway' => 'fake',
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->postJson('/api/v1/webhooks/payment', $payload, $this->signatureHeaders($body))->assertOk();

        $this->assertSame(10, (int) $product->fresh()->stock);
    }

    public function test_webhook_for_unknown_transaction_returns_404(): void
    {
        $payload = [
            'transaction_id' => 'never-seen',
            'status' => 'approved',
            'gateway' => 'fake',
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $response = $this->postJson('/api/v1/webhooks/payment', $payload, $this->signatureHeaders($body));

        $this->assertContains($response->status(), [404, 422]);
    }

    private function signatureHeaders(string $body): array
    {
        $ts = time();
        $hmac = hash_hmac('sha256', $ts . '.' . $body, (string) config('payment.webhook.secret'));

        return [
            'X-Signature-Timestamp' => (string) $ts,
            'X-Signature' => 't=' . $ts . ',v1=' . $hmac,
        ];
    }

    private function makeOrderWithItem(
        int $priceCents = 5000,
        int $quantity = 1,
        int $stock = 10,
        ?Product $product = null,
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
