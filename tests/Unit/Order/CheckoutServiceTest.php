<?php

declare(strict_types=1);

namespace Tests\Unit\Order;

use Catalog\Domain\Contracts\ProductRepositoryContract;
use Catalog\Domain\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Order\Application\DTOs\CheckoutInput;
use Order\Application\Services\CheckoutService;
use Order\Domain\Contracts\OrderRepositoryContract;
use Order\Domain\Enums\OrderStatus;
use Order\Domain\Exceptions\OutOfStockException;
use Payment\Domain\Contracts\PaymentGatewayContract;
use Payment\Domain\Contracts\PaymentRepositoryContract;
use Payment\Domain\Enums\PaymentStatus;
use Payment\Domain\ValueObjects\CreditCard;
use Tests\TestCase;

final class CheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_order_and_payment_with_pending_status(): void
    {
        $product = Product::factory()->create(['price_cents' => 5000, 'stock' => 10]);

        $service = $this->makeService();
        $result = $service->execute($this->makeInput([$product->id => 2]));

        $this->assertSame(OrderStatus::Pending, $result->order->status);
        $this->assertSame(10000, (int) $result->order->total_cents);
        $this->assertSame(PaymentStatus::Pending, $result->payment->status);
        $this->assertSame(10000, (int) $result->payment->amount_cents);
        $this->assertSame('fake', $result->payment->gateway);
        $this->assertStringStartsWith('fake_', (string) $result->payment->transaction_id);
    }

    public function test_it_throws_out_of_stock_when_item_exceeds_stock(): void
    {
        $product = Product::factory()->create(['price_cents' => 5000, 'stock' => 2]);

        $service = $this->makeService();

        $this->expectException(OutOfStockException::class);
        $this->expectExceptionMessage('has only 2 in stock, requested 5');

        $service->execute($this->makeInput([$product->id => 5]));
    }

    public function test_it_throws_out_of_stock_for_unknown_product(): void
    {
        $service = $this->makeService();

        $this->expectException(OutOfStockException::class);

        $service->execute($this->makeInput(['00000000-0000-4000-8000-000000000000' => 1]));
    }

    public function test_it_sums_totals_across_multiple_items(): void
    {
        $a = Product::factory()->create(['price_cents' => 1000, 'stock' => 10]);
        $b = Product::factory()->create(['price_cents' => 2500, 'stock' => 10]);

        $service = $this->makeService();
        $result = $service->execute($this->makeInput([
            $a->id => 3,
            $b->id => 2,
        ]));

        $this->assertSame(8000, (int) $result->order->total_cents);
    }

    private function makeService(): CheckoutService
    {
        return new CheckoutService(
            $this->app->make(ProductRepositoryContract::class),
            $this->app->make(OrderRepositoryContract::class),
            $this->app->make(PaymentRepositoryContract::class),
            $this->app->make(PaymentGatewayContract::class),
        );
    }

    private function makeInput(array $items): CheckoutInput
    {
        $lines = [];
        foreach ($items as $productId => $quantity) {
            $lines[] = ['productId' => $productId, 'quantity' => $quantity];
        }

        return new CheckoutInput(
            customerName: 'Test Customer',
            customerEmail: 'test@example.com',
            creditCard: new CreditCard('4111111111111111', 'TEST', '12/27', '123'),
            items: $lines,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
