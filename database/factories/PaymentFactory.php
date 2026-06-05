<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Order\Domain\Order;
use Payment\Domain\Enums\PaymentStatus;
use Payment\Domain\Payment;

final class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'gateway' => 'fake',
            'transaction_id' => 'fake_' . $this->faker->unique()->uuid(),
            'status' => PaymentStatus::Pending,
            'amount_cents' => 10000,
            'currency' => 'BRL',
            'card_last4' => '1111',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn() => ['status' => PaymentStatus::Approved]);
    }

    public function declined(): static
    {
        return $this->state(fn() => ['status' => PaymentStatus::Declined]);
    }
}
