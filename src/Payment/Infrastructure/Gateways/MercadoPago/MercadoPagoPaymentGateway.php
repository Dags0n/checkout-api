<?php

declare(strict_types=1);

namespace Payment\Infrastructure\Gateways\MercadoPago;

use Payment\Application\DTOs\PaymentAuthorizationInput;
use Payment\Application\DTOs\PaymentGatewayResponse;
use Payment\Domain\Contracts\PaymentGatewayContract;
use Payment\Domain\Enums\PaymentStatus;

final class MercadoPagoPaymentGateway implements PaymentGatewayContract
{
    private const CENTS_PER_UNIT = 100;

    public function __construct(
        private readonly MercadoPagoClient $client,
    ) {}

    public function charge(PaymentAuthorizationInput $input): PaymentGatewayResponse
    {
        $token = $this->client->createCardToken($input->creditCard);
        $bin = substr($input->creditCard->number, 0, 6);
        $paymentMethodId = $this->client->resolvePaymentMethodId($bin);

        $payment = $this->client->createPayment(
            [
                'transaction_amount' => $input->amountCents / self::CENTS_PER_UNIT,
                'token' => $token,
                'installments' => 1,
                'payment_method_id' => $paymentMethodId,
                'payer' => [
                    'email' => $input->customerEmail,
                ],
            ],
            idempotencyKey: $input->orderId,
        );

        return new PaymentGatewayResponse(
            transactionId: (string) ($payment['id'] ?? ''),
            status: self::mapStatus((string) ($payment['status'] ?? '')),
            metadata: [
                'status_detail' => $payment['status_detail'] ?? null,
                'payment_method_id' => $payment['payment_method_id'] ?? $paymentMethodId,
                'last_four' => $payment['card']['last_four_digits'] ?? $input->creditCard->last4(),
                'installments' => $payment['installments'] ?? 1,
            ],
        );
    }

    public static function mapStatus(string $status): PaymentStatus
    {
        return match ($status) {
            'approved' => PaymentStatus::Approved,
            'rejected', 'cancelled', 'refunded', 'charged_back' => PaymentStatus::Declined,
            default => PaymentStatus::Pending,
        };
    }
}
