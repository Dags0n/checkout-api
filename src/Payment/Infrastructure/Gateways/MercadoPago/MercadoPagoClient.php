<?php

declare(strict_types=1);

namespace Payment\Infrastructure\Gateways\MercadoPago;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Payment\Domain\ValueObjects\CreditCard;
use Shared\Domain\Exceptions\DomainException;

final class MercadoPagoClient
{
    public function __construct(
        private readonly string $apiUrl,
        private readonly string $accessToken,
    ) {}

    public function createCardToken(CreditCard $card): string
    {
        [$month, $year] = $this->splitExpiry($card->expiry);

        $response = $this->request()->post('/v1/card_tokens', [
            'card_number' => $card->number,
            'security_code' => $card->cvv,
            'expiration_month' => $month,
            'expiration_year' => $year,
            'cardholder' => [
                'name' => $card->holderName,
            ],
        ]);

        $this->ensureSuccessful($response, 'card token creation');

        $tokenId = $response->json('id');
        if (! is_string($tokenId) || $tokenId === '') {
            throw new DomainException(
                'MercadoPago did not return a card token id.',
                'MP_INVALID_TOKEN_RESPONSE',
            );
        }

        return $tokenId;
    }

    public function resolvePaymentMethodId(string $bin): string
    {
        $response = $this->request()->get('/v1/payment_methods/search', [
            'bin' => $bin,
        ]);

        $this->ensureSuccessful($response, 'payment method lookup');

        $methodId = $response->json('results.0.id');
        if (! is_string($methodId) || $methodId === '') {
            throw new DomainException(
                sprintf('MercadoPago could not resolve a payment method for BIN %s.', $bin),
                'MP_UNKNOWN_PAYMENT_METHOD',
            );
        }

        return $methodId;
    }

    public function createPayment(array $payload, string $idempotencyKey): array
    {
        $response = $this->request()
            ->withHeaders(['X-Idempotency-Key' => $idempotencyKey])
            ->post('/v1/payments', $payload);

        $this->ensureSuccessful($response, 'payment creation');

        return $response->json();
    }

    public function getPayment(string $id): array
    {
        $response = $this->request()->get("/v1/payments/{$id}");

        $this->ensureSuccessful($response, 'payment lookup');

        return $response->json();
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->apiUrl)
            ->withToken($this->accessToken)
            ->acceptJson()
            ->asJson();
    }

    private function ensureSuccessful(Response $response, string $action): void
    {
        if ($response->failed()) {
            throw new DomainException(
                sprintf(
                    'MercadoPago %s failed with status %d.',
                    $action,
                    $response->status(),
                ),
                'MP_API_ERROR',
            );
        }
    }

    private function splitExpiry(string $expiry): array
    {
        [$month, $year] = explode('/', $expiry);

        return [(int) $month, 2000 + (int) $year];
    }
}
