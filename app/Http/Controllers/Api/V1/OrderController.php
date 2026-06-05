<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\OrderResource;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Order\Domain\Contracts\OrderRepositoryContract;
use Order\Domain\Order;

final class OrderController extends Controller
{
    public function __construct(private readonly OrderRepositoryContract $orders) {}

    public function show(string $id): JsonResponse
    {
        $order = $this->orders->findByIdWithRelations($id)
            ?? throw (new ModelNotFoundException)->setModel(Order::class, [$id]);

        return response()->json([
            'data' => OrderResource::make($order),
            'meta' => [],
        ]);
    }
}
