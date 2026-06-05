<?php

declare(strict_types=1);

namespace Order\Infrastructure;

use Illuminate\Support\Collection;
use Order\Domain\Contracts\OrderRepositoryContract;
use Order\Domain\Enums\OrderStatus;
use Order\Domain\Order;

final class EloquentOrderRepository implements OrderRepositoryContract
{
    public function findById(string $id): ?Order
    {
        return Order::query()->find($id);
    }

    public function findByIdWithRelations(string $id): ?Order
    {
        return Order::query()
            ->with(['items.product', 'payments'])
            ->find($id);
    }

    public function save(Order $order): Order
    {
        $order->save();

        return $order;
    }

    public function findByStatus(OrderStatus $status, int $limit = 100): Collection
    {
        return Order::query()
            ->with('payments')
            ->where('status', $status->value)
            ->limit($limit)
            ->get();
    }
}
