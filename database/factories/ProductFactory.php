<?php

declare(strict_types=1);

namespace Database\Factories;

use Catalog\Domain\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

final class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'sku' => strtoupper($this->faker->unique()->bothify('SKU-####-???')),
            'price_cents' => $this->faker->numberBetween(1990, 29900),
            'currency' => 'BRL',
            'stock' => $this->faker->numberBetween(5, 50),
        ];
    }
}
