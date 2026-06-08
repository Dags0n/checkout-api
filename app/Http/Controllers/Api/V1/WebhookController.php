<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Payment\Application\Services\PaymentWebhookService;
use Payment\Domain\Contracts\WebhookTranslatorContract;

final class WebhookController extends Controller
{
    public function __construct(
        private readonly PaymentWebhookService $webhook,
        private readonly WebhookTranslatorContract $translator,
    ) {}

    public function payment(Request $request): JsonResponse
    {
        $payload = $this->translator->translate(
            $request->json()->all(),
            $request->query(),
        );

        if ($payload !== null) {
            $this->webhook->process($payload);
        }

        return response()->json([
            'data' => [
                'processed' => $payload !== null,
            ],
            'meta' => [],
        ]);
    }
}
