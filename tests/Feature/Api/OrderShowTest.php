<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Catalog\Domain\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Order\Domain\Order;
use Payment\Domain\Enums\PaymentStatus;
use Payment\Domain\Payment;
use Tests\TestCase;

final class OrderShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_own_order(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $product = Product::factory()->create(['price_cents' => 5000, 'stock' => 10]);
        $order = Order::factory()->create();
        $order->addItem($product, 2);
        Payment::factory()->create([
            'order_id' => $order->id,
            'amount_cents' => 10000,
            'status' => PaymentStatus::Pending,
        ]);

        $response = $this->getJson("/api/v1/orders/{$order->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $order->id);
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.total_amount.amount_cents', 10000);
        $response->assertJsonPath('data.payment.status', 'pending');
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $order = Order::factory()->create();

        $this->getJson("/api/v1/orders/{$order->id}")->assertStatus(401);
    }

    public function test_unknown_id_returns_404(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/orders/00000000-0000-4000-8000-000000000000');

        $response->assertStatus(404);
        $response->assertJsonPath('meta.code', 'ORDER_NOT_FOUND');
    }

    public function test_response_includes_payment_when_present(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $order = Order::factory()->create();
        Payment::factory()->create([
            'order_id' => $order->id,
            'transaction_id' => 'fake_test_tx',
            'card_last4' => '1111',
            'status' => PaymentStatus::Approved,
        ]);

        $response = $this->getJson("/api/v1/orders/{$order->id}");

        $response->assertOk();
        $response->assertJsonPath('data.payment.transaction_id', 'fake_test_tx');
        $response->assertJsonPath('data.payment.card_last4', '1111');
        $response->assertJsonPath('data.payment.status', 'approved');
    }
}
