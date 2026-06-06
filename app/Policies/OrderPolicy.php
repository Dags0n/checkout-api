<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Order\Domain\Order;

final class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $order->user_id === $user->id;
    }
}
