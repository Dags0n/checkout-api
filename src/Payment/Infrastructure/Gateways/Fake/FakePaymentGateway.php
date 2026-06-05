<?php

declare(strict_types=1);

namespace Payment\Infrastructure\Gateways\Fake;

use Illuminate\Support\Str;
use Payment\Application\DTOs\PaymentAuthorizationInput;
use Payment\Application\DTOs\PaymentGatewayResponse;
use Payment\Domain\Contracts\PaymentGatewayContract;
use Payment\Domain\Enums\PaymentStatus;

final class FakePaymentGateway implements PaymentGatewayContract
{
    public function charge(PaymentAuthorizationInput $input): PaymentGatewayResponse
    {
        sleep(random_int(1, 2));

        return new PaymentGatewayResponse(
            transactionId: 'fake_' . Str::uuid()->toString(),
            status: PaymentStatus::Pending,
            metadata: [
                'card_last4' => $input->creditCard->last4(),
                'amount_cents' => $input->amountCents,
                'customer_email' => $input->customerEmail,
            ],
        );
    }
}
