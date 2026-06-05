<?php

declare(strict_types=1);

namespace Payment\Application\Services;

use Catalog\Domain\Contracts\ProductRepositoryContract;
use Illuminate\Support\Facades\DB;
use Order\Application\DTOs\WebhookPayload;
use Order\Domain\Order;
use Payment\Domain\Contracts\PaymentRepositoryContract;
use Payment\Domain\Payment;
use Shared\Domain\Exceptions\DomainException;

final class PaymentWebhookService
{
    public function __construct(
        private readonly PaymentRepositoryContract $payments,
        private readonly ProductRepositoryContract $products,
    ) {}

    public function process(WebhookPayload $payload): void
    {
        DB::transaction(function () use ($payload): void {
            $payment = $this->payments->findByTransactionId($payload->transactionId);

            if ($payment === null) {
                throw new DomainException(
                    sprintf('No payment found for transaction_id "%s".', $payload->transactionId),
                    'PAYMENT_NOT_FOUND',
                );
            }

            if ($payment->status?->isFinal()) {
                return;
            }

            $order = $payment->order;
            if (! $order) {
                throw new DomainException(
                    sprintf('Payment %s has no associated order.', (string) $payment->id),
                    'ORPHAN_PAYMENT',
                );
            }

            if ($order->isFinalized()) {
                return;
            }

            if ($payload->status === 'approved') {
                $this->approve($payment, $order);
            } elseif ($payload->status === 'declined') {
                $this->decline($payment, $order);
            } else {
                throw new DomainException(
                    sprintf('Unknown webhook status "%s".', $payload->status),
                    'INVALID_WEBHOOK_STATUS',
                );
            }
        });
    }

    private function approve(Payment $payment, Order $order): void
    {
        foreach ($order->items()->with('product')->get() as $item) {
            $lockedProduct = $this->products->lockForUpdate($item->product_id);
            if ($lockedProduct === null) {
                throw new DomainException(
                    sprintf('Product %s not found during stock decrement.', $item->product_id),
                    'PRODUCT_NOT_FOUND',
                );
            }
            $lockedProduct->decrementStock((int) $item->quantity);
            $this->products->save($lockedProduct);
        }

        $payment->markApproved();
        $this->payments->save($payment);

        $order->confirmPayment();
        $this->saveOrderSafely($order);
    }

    private function decline(Payment $payment, Order $order): void
    {
        $payment->markDeclined();
        $this->payments->save($payment);

        $order->failPayment();
        $this->saveOrderSafely($order);
    }

    private function saveOrderSafely(Order $order): void
    {
        $order->save();
    }
}
