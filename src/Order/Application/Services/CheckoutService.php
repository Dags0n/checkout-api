<?php

declare(strict_types=1);

namespace Order\Application\Services;

use Catalog\Domain\Contracts\ProductRepositoryContract;
use Illuminate\Support\Facades\DB;
use Order\Application\DTOs\CheckoutInput;
use Order\Application\DTOs\CheckoutResult;
use Order\Domain\Contracts\OrderRepositoryContract;
use Order\Domain\Enums\OrderStatus;
use Order\Domain\Exceptions\OutOfStockException;
use Order\Domain\Order;
use Payment\Application\DTOs\PaymentAuthorizationInput;
use Payment\Domain\Contracts\PaymentGatewayContract;
use Payment\Domain\Contracts\PaymentRepositoryContract;
use Payment\Domain\Enums\PaymentStatus;
use Payment\Domain\Payment;

final class CheckoutService
{
    public function __construct(
        private readonly ProductRepositoryContract $products,
        private readonly OrderRepositoryContract $orders,
        private readonly PaymentRepositoryContract $payments,
        private readonly PaymentGatewayContract $gateway,
    ) {}

    public function execute(CheckoutInput $input): CheckoutResult
    {
        $order = DB::transaction(function () use ($input): Order {
            $order = $this->orders->save(new Order([
                'customer_name' => $input->customerName,
                'customer_email' => $input->customerEmail,
                'status' => OrderStatus::Pending,
                'total_cents' => 0,
                'currency' => 'BRL',
            ]));

            foreach ($input->items as $line) {
                $product = $this->products->findById($line['productId'])
                    ?? throw OutOfStockException::for($line['productId'], 0, $line['quantity']);

                if (! $product->hasStock($line['quantity'])) {
                    throw OutOfStockException::for(
                        $line['productId'],
                        $product->stock,
                        $line['quantity'],
                    );
                }

                $order->addItem($product, $line['quantity']);
            }

            return $order->refresh();
        });

        $response = $this->gateway->charge(new PaymentAuthorizationInput(
            orderId: (string) $order->id,
            amountCents: (int) $order->total_cents,
            currency: $order->currency,
            creditCard: $input->creditCard,
            customerName: $input->customerName,
            customerEmail: $input->customerEmail,
        ));

        $payment = DB::transaction(function () use ($order, $input, $response): Payment {
            $payment = new Payment([
                'order_id' => $order->id,
                'gateway' => $this->gatewayName(),
                'status' => PaymentStatus::Pending,
                'amount_cents' => (int) $order->total_cents,
                'currency' => $order->currency,
                'card_last4' => $input->creditCard->last4(),
            ]);
            $payment->transaction_id = $response->transactionId;
            $payment->gateway_metadata = $response->metadata;

            return $this->payments->save($payment);
        });

        return new CheckoutResult($order, $payment);
    }

    private function gatewayName(): string
    {
        return (string) config('payment.gateway', 'fake');
    }
}
