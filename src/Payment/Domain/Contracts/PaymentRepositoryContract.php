<?php

declare(strict_types=1);

namespace Payment\Domain\Contracts;

use Payment\Domain\Payment;

interface PaymentRepositoryContract
{
    public function findById(string $id): ?Payment;

    public function findByTransactionId(string $transactionId): ?Payment;

    public function save(Payment $payment): Payment;
}
