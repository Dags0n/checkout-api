<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog;

use Catalog\Domain\Product;
use Order\Domain\Exceptions\OutOfStockException;
use PHPUnit\Framework\TestCase;

final class ProductTest extends TestCase
{
    public function test_it_reports_stock_availability(): void
    {
        $product = new Product;
        $product->stock = 5;

        $this->assertTrue($product->hasStock(1));
        $this->assertTrue($product->hasStock(5));
        $this->assertFalse($product->hasStock(6));
    }

    public function test_it_decrements_stock_when_sufficient(): void
    {
        $product = new Product;
        $product->stock = 10;
        $product->decrementStock(3);

        $this->assertSame(7, $product->stock);
    }

    public function test_it_throws_out_of_stock_when_insufficient(): void
    {
        $product = new Product;
        $product->id = 'prod-1';
        $product->stock = 2;

        $this->expectException(OutOfStockException::class);
        $this->expectExceptionMessage('Product prod-1 has only 2 in stock, requested 5.');

        $product->decrementStock(5);
    }

    public function test_out_of_stock_exception_carries_metadata(): void
    {
        $product = new Product;
        $product->id = 'prod-1';
        $product->stock = 0;

        try {
            $product->decrementStock(1);
            $this->fail('Expected OutOfStockException');
        } catch (OutOfStockException $e) {
            $this->assertSame('prod-1', $e->productId);
            $this->assertSame(0, $e->available);
            $this->assertSame(1, $e->requested);
            $this->assertSame('OUT_OF_STOCK', $e->errorCode);
        }
    }

    public function test_it_rejects_non_positive_quantity(): void
    {
        $product = new Product;
        $product->stock = 5;

        $this->expectException(\InvalidArgumentException::class);
        $product->decrementStock(0);
    }
}
