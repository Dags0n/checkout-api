<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Order\Application\DTOs\WebhookPayload;
use Order\Domain\Contracts\OrderRepositoryContract;
use Order\Domain\Enums\OrderStatus;
use Payment\Application\Services\PaymentWebhookService;
use Payment\Domain\Payment;
use Throwable;

final class SimulatePaymentWebhookCommand extends Command
{
    protected $signature = 'app:simulate-payment-webhook {--limit=100}';

    protected $description = 'Process pending orders as if the payment gateway sent a webhook.';

    public function handle(
        OrderRepositoryContract $orders,
        PaymentWebhookService $webhook,
    ): int {
        $limit = (int) $this->option('limit');
        $pending = $orders->findByStatus(OrderStatus::Pending, $limit);

        $this->info(sprintf('Found %d pending order(s).', $pending->count()));

        $approved = 0;
        $declined = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($pending as $order) {
            $payment = $order->latestPayment();
            if (! $payment || $payment->status?->isFinal()) {
                $skipped++;

                continue;
            }

            $approved = $this->resolveStatus($payment) === 'approved' ? $approved + 1 : $approved;
            $declined = $this->resolveStatus($payment) === 'declined' ? $declined + 1 : $declined;

            try {
                $webhook->process(new WebhookPayload(
                    transactionId: (string) $payment->transaction_id,
                    status: $this->resolveStatus($payment),
                    gateway: (string) $payment->gateway,
                ));
                $this->line(sprintf('  - %s → %s', $order->id, $this->resolveStatus($payment)));
            } catch (Throwable $e) {
                $failed++;
                $this->error(sprintf('  - %s failed: %s', $order->id, $e->getMessage()));
            }
        }

        $this->info(sprintf('Summary: %d approved, %d declined, %d skipped, %d failed.', $approved, $declined, $skipped, $failed));

        return self::SUCCESS;
    }

    private function resolveStatus(Payment $payment): string
    {
        $last4 = (string) $payment->card_last4;
        if ($last4 === '') {
            return 'declined';
        }
        $lastDigit = (int) substr($last4, -1);

        return $lastDigit % 2 === 0 ? 'approved' : 'declined';
    }
}
