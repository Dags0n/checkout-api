<?php

declare(strict_types=1);

namespace Catalog\Domain\Contracts;

use Catalog\Domain\Product;

interface ProductRepositoryContract
{
    public function findById(string $id): ?Product;

    public function lockForUpdate(string $id): ?Product;

    public function save(Product $product): void;
}
