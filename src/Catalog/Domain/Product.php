<?php

declare(strict_types=1);

namespace Catalog\Domain;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Order\Domain\Exceptions\OutOfStockException;
use Shared\Domain\Concerns\HasUuid;

#[Fillable(['name', 'description', 'sku', 'price_cents', 'currency', 'stock'])]
class Product extends Model
{
    use HasFactory;

    use HasUuid;

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'stock' => 'integer',
        ];
    }

    public function hasStock(int $quantity): bool
    {
        return $this->stock >= $quantity;
    }

    public function decrementStock(int $quantity): void
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Quantity must be positive.');
        }

        if (! $this->hasStock($quantity)) {
            throw OutOfStockException::for($this->id, $this->stock, $quantity);
        }

        $this->stock -= $quantity;
    }
}
