<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Order\Application\DTOs\CheckoutInput;
use Order\Application\Services\CheckoutService;
use Payment\Domain\ValueObjects\CreditCard;

final class CheckoutController extends Controller
{
    public function __construct(private readonly CheckoutService $checkout) {}

    public function store(CheckoutRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $input = new CheckoutInput(
            customerName: $user->name,
            customerEmail: $user->email,
            creditCard: new CreditCard(
                number: $validated['credit_card']['number'],
                holderName: $validated['credit_card']['holder_name'],
                expiry: $validated['credit_card']['expiry'],
                cvv: $validated['credit_card']['cvv'],
            ),
            items: array_map(
                fn (array $i): array => ['productId' => $i['product_id'], 'quantity' => (int) $i['quantity']],
                $validated['items'],
            ),
        );

        $result = $this->checkout->execute($input);

        return response()->json([
            'data' => [
                'order' => OrderResource::make($result->order),
                'payment' => PaymentResource::make($result->payment),
            ],
            'meta' => [],
        ], 201);
    }
}
