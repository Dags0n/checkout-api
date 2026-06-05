<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Catalog\Domain\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_checkout(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $product = Product::factory()->create(['price_cents' => 5000, 'stock' => 10]);

        $response = $this->postJson('/api/v1/checkout', [
            'customer' => [
                'name' => 'João Silva',
                'email' => 'joao@example.com',
            ],
            'credit_card' => [
                'number' => '4111111111111111',
                'holder_name' => 'JOAO SILVA',
                'expiry' => '12/27',
                'cvv' => '123',
            ],
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.order.status', 'pending');
        $response->assertJsonPath('data.order.total_amount.amount_cents', 10000);
        $response->assertJsonPath('data.payment.status', 'pending');
        $response->assertJsonPath('data.payment.gateway', 'fake');
    }

    public function test_unauthenticated_user_cannot_checkout(): void
    {
        $product = Product::factory()->create();

        $response = $this->postJson('/api/v1/checkout', [
            'customer' => ['name' => 'X', 'email' => 'x@x.com'],
            'credit_card' => [
                'number' => '4111111111111111',
                'holder_name' => 'X',
                'expiry' => '12/27',
                'cvv' => '123',
            ],
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response->assertStatus(401);
    }

    public function test_invalid_payload_returns_422(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/checkout', [
            'customer' => ['name' => '', 'email' => 'not-an-email'],
            'credit_card' => [
                'number' => 'abc',
                'holder_name' => '',
                'expiry' => '99/99',
                'cvv' => '1',
            ],
            'items' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['data', 'meta' => ['code', 'message', 'errors']]);
    }

    public function test_insufficient_stock_returns_422_with_out_of_stock_code(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $product = Product::factory()->create(['price_cents' => 5000, 'stock' => 1]);

        $response = $this->postJson('/api/v1/checkout', [
            'customer' => ['name' => 'João', 'email' => 'joao@example.com'],
            'credit_card' => [
                'number' => '4111111111111111',
                'holder_name' => 'JOAO',
                'expiry' => '12/27',
                'cvv' => '123',
            ],
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('meta.code', 'OUT_OF_STOCK');
    }

    public function test_unknown_product_returns_422(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/checkout', [
            'customer' => ['name' => 'João', 'email' => 'joao@example.com'],
            'credit_card' => [
                'number' => '4111111111111111',
                'holder_name' => 'JOAO',
                'expiry' => '12/27',
                'cvv' => '123',
            ],
            'items' => [['product_id' => '00000000-0000-4000-8000-000000000000', 'quantity' => 1]],
        ]);

        $response->assertStatus(422);
    }
}
