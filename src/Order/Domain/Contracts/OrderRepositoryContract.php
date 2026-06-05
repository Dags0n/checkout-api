<?php

declare(strict_types=1);

namespace Order\Domain\Contracts;

use Illuminate\Support\Collection;
use Order\Domain\Enums\OrderStatus;
use Order\Domain\Order;

interface OrderRepositoryContract
{
    public function findById(string $id): ?Order;

    public function findByIdWithRelations(string $id): ?Order;

    public function save(Order $order): Order;

    public function findByStatus(OrderStatus $status, int $limit = 100): Collection;
}
