<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\WebhookRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Order\Application\DTOs\WebhookPayload;
use Payment\Application\Services\PaymentWebhookService;

final class WebhookController extends Controller
{
    public function __construct(private readonly PaymentWebhookService $webhook) {}

    public function payment(WebhookRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->webhook->process(new WebhookPayload(
            transactionId: $validated['transaction_id'],
            status: $validated['status'],
            gateway: $validated['gateway'],
        ));

        return response()->json([
            'data' => ['processed' => true],
            'meta' => [],
        ]);
    }
}
