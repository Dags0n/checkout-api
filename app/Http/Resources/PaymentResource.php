<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Payment\Domain\Payment;

final class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gateway' => $this->gateway,
            'transaction_id' => $this->transaction_id,
            'status' => $this->status?->value,
            'amount_cents' => (int) $this->amount_cents,
            'currency' => $this->currency,
            'card_last4' => $this->card_last4,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
