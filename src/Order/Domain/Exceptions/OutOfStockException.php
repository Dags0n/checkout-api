<?php

declare(strict_types=1);

namespace Order\Domain\Exceptions;

use Shared\Domain\Exceptions\DomainException;

final class OutOfStockException extends DomainException
{
    public function __construct(
        public readonly string $productId,
        public readonly int $available,
        public readonly int $requested,
    ) {
        parent::__construct(
            sprintf(
                'Product %s has only %d in stock, requested %d.',
                $productId,
                $available,
                $requested,
            ),
            'OUT_OF_STOCK',
        );
    }

    public static function for(string $productId, int $available, int $requested): self
    {
        return new self($productId, $available, $requested);
    }
}
