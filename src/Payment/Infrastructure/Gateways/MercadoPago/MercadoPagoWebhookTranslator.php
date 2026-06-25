<?php

declare(strict_types=1);

namespace Payment\Infrastructure\Gateways\MercadoPago;

use Order\Application\DTOs\WebhookPayload;
use Payment\Domain\Contracts\WebhookTranslatorContract;
use Payment\Domain\Enums\PaymentStatus;
use Shared\Domain\Exceptions\DomainException;

final class MercadoPagoWebhookTranslator implements WebhookTranslatorContract
{
    private const GATEWAY = 'mercadopago';

    public function __construct(private readonly MercadoPagoClient $client) {}

    public function translate(array $body, array $query): ?WebhookPayload
    {
        $paymentId = $this->extractPaymentId($body, $query);
        if ($paymentId === null) {
            throw new DomainException(
                'MercadoPago webhook is missing the payment id.',
                'INVALID_WEBHOOK_PAYLOAD',
            );
        }

        $payment = $this->client->getPayment($paymentId);
        $status = MercadoPagoPaymentGateway::mapStatus((string) ($payment['status'] ?? ''));

        if ($status === PaymentStatus::Pending) {
            return null;
        }

        return new WebhookPayload(
            transactionId: $paymentId,
            status: $status === PaymentStatus::Approved ? 'approved' : 'declined',
            gateway: self::GATEWAY,
        );
    }

    private function extractPaymentId(array $body, array $query): ?string
    {
        $candidates = [
            $body['data']['id'] ?? null,
            $query['data_id'] ?? null,
            $query['id'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
            if (is_int($candidate)) {
                return (string) $candidate;
            }
        }

        return null;
    }
}
