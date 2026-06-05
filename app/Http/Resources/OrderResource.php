<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payment = $this->latestPayment();

        return [
            'id' => $this->id,
            'status' => $this->status?->value,
            'total_amount' => [
                'amount_cents' => (int) $this->total_cents,
                'currency' => $this->currency,
                'formatted' => $this->formatMoney((int) $this->total_cents),
            ],
            'customer' => [
                'name' => $this->customer_name,
                'email' => $this->customer_email,
            ],
            'items' => $this->whenLoaded('items', fn() => $this->items->map(fn($i) => [
                'product_id' => $i->product_id,
                'name' => $i->relationLoaded('product') ? $i->product?->name : null,
                'quantity' => (int) $i->quantity,
                'unit_price_cents' => (int) $i->unit_price_cents,
                'subtotal_cents' => (int) $i->subtotal_cents,
            ])->all()),
            'payment' => $payment ? [
                'id' => $payment->id,
                'gateway' => $payment->gateway,
                'transaction_id' => $payment->transaction_id,
                'status' => $payment->status?->value,
                'amount_cents' => (int) $payment->amount_cents,
                'card_last4' => $payment->card_last4,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function formatMoney(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.');
    }
}
