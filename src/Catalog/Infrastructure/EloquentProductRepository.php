<?php

declare(strict_types=1);

namespace Catalog\Infrastructure;

use Catalog\Domain\Contracts\ProductRepositoryContract;
use Catalog\Domain\Product;

final class EloquentProductRepository implements ProductRepositoryContract
{
    public function findById(string $id): ?Product
    {
        return Product::query()->find($id);
    }

    public function lockForUpdate(string $id): ?Product
    {
        $query = Product::query();

        return $query
            ->whereKey($id)
            ->lockForUpdate()
            ->first();
    }

    public function save(Product $product): void
    {
        $product->save();
    }
}
