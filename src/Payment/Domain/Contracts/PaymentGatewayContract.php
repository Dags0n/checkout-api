<?php

declare(strict_types=1);

namespace Payment\Domain\Contracts;

use Payment\Application\DTOs\PaymentAuthorizationInput;
use Payment\Application\DTOs\PaymentGatewayResponse;

interface PaymentGatewayContract
{
    public function charge(PaymentAuthorizationInput $input): PaymentGatewayResponse;
}
