<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Catalog\Domain\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Order\Domain\Enums\OrderStatus;
use Order\Domain\Order;
use Payment\Domain\Contracts\WebhookSignatureVerifierContract;
use Payment\Domain\Contracts\WebhookTranslatorContract;
use Payment\Domain\Enums\PaymentStatus;
use Payment\Domain\Payment;
use Payment\Infrastructure\Gateways\MercadoPago\MercadoPagoSignatureVerifier;
use Payment\Infrastructure\Gateways\MercadoPago\MercadoPagoWebhookTranslator;
use Tests\TestCase;

final class MercadoPagoWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'mp-webhook-secret';

    private const API = 'https://api.mercadopago.com';

    protected function setUp(): void
    {
        parent::setUp();

        config(['payment.gateways.mercadopago.webhook_secret' => self::SECRET]);

        $this->app->bind(WebhookSignatureVerifierContract::class, MercadoPagoSignatureVerifier::class);
        $this->app->bind(WebhookTranslatorContract::class, MercadoPagoWebhookTranslator::class);
    }

    public function test_signed_mp_webhook_fetches_status_and_settles_the_order(): void
    {
        $product = Product::factory()->create(['price_cents' => 5000, 'stock' => 10]);
        $order = Order::factory()->create();
        $order->addItem($product, 3);
        $this->makeMpPayment($order->refresh(), '123456');

        Http::fake([
            self::API . '/v1/payments/123456' => Http::response(['id' => 123456, 'status' => 'approved'], 200),
        ]);

        $response = $this->postMpWebhook('123456');

        $response->assertOk();
        $response->assertJsonPath('data.processed', true);
        $this->assertSame(PaymentStatus::Approved, Payment::first()->fresh()->status);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(7, (int) $product->fresh()->stock);
    }

    public function test_pending_mp_webhook_is_acked_without_settling(): void
    {
        $product = Product::factory()->create(['price_cents' => 5000, 'stock' => 10]);
        $order = Order::factory()->create();
        $order->addItem($product, 1);
        $this->makeMpPayment($order->refresh(), '777');

        Http::fake([
            self::API . '/v1/payments/777' => Http::response(['id' => 777, 'status' => 'in_process'], 200),
        ]);

        $response = $this->postMpWebhook('777');

        $response->assertOk();
        $response->assertJsonPath('data.processed', false);
        $this->assertSame(PaymentStatus::Pending, Payment::first()->fresh()->status);
        $this->assertSame(10, (int) $product->fresh()->stock);
    }

    public function test_mp_webhook_with_invalid_signature_is_rejected(): void
    {
        $headers = $this->signedHeaders('123456', time());
        $headers['X-Signature'] = 'ts=' . time() . ',v1=' . str_repeat('0', 64);

        $response = $this->postJson(
            '/api/v1/webhooks/payment?data.id=123456',
            ['type' => 'payment', 'data' => ['id' => '123456']],
            $headers,
        );

        $response->assertStatus(401);
        $response->assertJsonPath('meta.code', 'INVALID_SIGNATURE');
    }

    private function makeMpPayment(Order $order, string $transactionId): Payment
    {
        return Payment::factory()->create([
            'order_id' => $order->id,
            'gateway' => 'mercadopago',
            'transaction_id' => $transactionId,
            'amount_cents' => (int) $order->total_cents,
            'status' => PaymentStatus::Pending,
        ]);
    }

    private function postMpWebhook(string $paymentId): TestResponse
    {
        return $this->postJson(
            "/api/v1/webhooks/payment?data.id={$paymentId}",
            ['type' => 'payment', 'data' => ['id' => $paymentId]],
            $this->signedHeaders($paymentId, time()),
        );
    }

    private function signedHeaders(string $dataId, int $ts): array
    {
        $manifest = sprintf('id:%s;request-id:%s;ts:%d;', strtolower($dataId), 'req-1', $ts);
        $hmac = hash_hmac('sha256', $manifest, self::SECRET);

        return [
            'X-Request-Id' => 'req-1',
            'X-Signature' => 'ts=' . $ts . ',v1=' . $hmac,
        ];
    }
}
