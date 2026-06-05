<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Order\Domain\Enums\OrderStatus;
use Order\Domain\Order;

final class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'customer_name' => $this->faker->name(),
            'customer_email' => $this->faker->safeEmail(),
            'status' => OrderStatus::Pending,
            'total_cents' => 0,
            'currency' => 'BRL',
        ];
    }

    public function paid(): static
    {
        return $this->state(fn() => ['status' => OrderStatus::Paid]);
    }

    public function failed(): static
    {
        return $this->state(fn() => ['status' => OrderStatus::Failed]);
    }
}
