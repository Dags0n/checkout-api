<?php

declare(strict_types=1);

namespace Payment\Infrastructure\Persistence;

use Payment\Domain\Contracts\PaymentRepositoryContract;
use Payment\Domain\Payment;

final class EloquentPaymentRepository implements PaymentRepositoryContract
{
    public function findById(string $id): ?Payment
    {
        return Payment::query()->find($id);
    }

    public function findByTransactionId(string $transactionId): ?Payment
    {
        return Payment::query()
            ->where('transaction_id', $transactionId)
            ->first();
    }

    public function save(Payment $payment): Payment
    {
        $payment->save();

        return $payment;
    }
}
