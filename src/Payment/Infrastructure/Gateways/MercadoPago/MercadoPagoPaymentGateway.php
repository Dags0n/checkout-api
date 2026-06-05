<?php

declare(strict_types=1);

namespace Payment\Infrastructure\Gateways\MercadoPago;

use Payment\Application\DTOs\PaymentAuthorizationInput;
use Payment\Application\DTOs\PaymentGatewayResponse;
use Payment\Domain\Contracts\PaymentGatewayContract;

/**
 * MercadoPago integration skeleton.
 */
final class MercadoPagoPaymentGateway implements PaymentGatewayContract
{
    public function __construct(
        // private readonly MercadoPagoClient $client,
        // private readonly string $apiUrl,
        // private readonly string $accessToken,
    ) {}

    public function charge(PaymentAuthorizationInput $input): PaymentGatewayResponse
    {
        throw new \LogicException(
            'MercadoPagoPaymentGateway::charge() is not implemented. '
                . 'Wire a real Http call to MP and return a PaymentGatewayResponse.'
        );
    }
}
