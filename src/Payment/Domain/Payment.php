<?php

declare(strict_types=1);

namespace Payment\Domain;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Order\Domain\Order;
use Payment\Domain\Enums\PaymentStatus;
use Payment\Domain\Exceptions\InvalidPaymentStateException;
use Shared\Domain\Concerns\HasUuid;

#[Fillable([
    'order_id',
    'gateway',
    'transaction_id',
    'status',
    'amount_cents',
    'currency',
    'card_last4',
    'gateway_metadata',
])]
class Payment extends Model
{
    use HasFactory;

    use HasUuid;

    protected static function newFactory(): PaymentFactory
    {
        return PaymentFactory::new();
    }

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount_cents' => 'integer',
            'gateway_metadata' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function markPending(string $transactionId, ?string $cardLast4 = null): void
    {
        if ($this->status !== null) {
            throw InvalidPaymentStateException::for((string) $this->id, (string) $this->status?->value, 'pending');
        }

        $this->transaction_id = $transactionId;
        if ($cardLast4 !== null) {
            $this->card_last4 = $cardLast4;
        }
        $this->status = PaymentStatus::Pending;
    }

    public function markApproved(): void
    {
        $this->transitionTo(PaymentStatus::Approved);
    }

    public function markDeclined(): void
    {
        $this->transitionTo(PaymentStatus::Declined);
    }

    private function transitionTo(PaymentStatus $target): void
    {
        if ($this->status?->isFinal()) {
            throw InvalidPaymentStateException::for((string) $this->id, (string) $this->status->value, $target->value);
        }

        $this->status = $target;
    }
}
