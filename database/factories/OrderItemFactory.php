<?php

declare(strict_types=1);

namespace Database\Factories;

use Catalog\Domain\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Order\Domain\Order;
use Order\Domain\OrderItem;

final class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $unitPrice = $this->faker->numberBetween(1990, 19900);
        $quantity = $this->faker->numberBetween(1, 3);

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'quantity' => $quantity,
            'unit_price_cents' => $unitPrice,
            'subtotal_cents' => $unitPrice * $quantity,
        ];
    }
}
