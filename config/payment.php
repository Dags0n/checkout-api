<?php

declare(strict_types=1);
use Payment\Infrastructure\Gateways\Fake\FakePaymentGateway;
use Payment\Infrastructure\Gateways\Fake\FakeSignatureVerifier;
use Payment\Infrastructure\Gateways\MercadoPago\MercadoPagoPaymentGateway;
use Payment\Infrastructure\Gateways\MercadoPago\MercadoPagoSignatureVerifier;

return [

    /*
    |--------------------------------------------------------------------------
    | Gateway Driver
    |--------------------------------------------------------------------------
    |
    | The active payment gateway. Supported values:
    |   - "fake"        : Simulated gateway (used for development/tests)
    |   - "mercadopago" : MercadoPago integration (production-ready stub)
    |
    */

    'gateway' => env('PAYMENT_GATEWAY', 'fake'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Signature Verification
    |--------------------------------------------------------------------------
    |
    | Shared secret used to verify HMAC-SHA256 signatures of incoming
    | webhook payloads. The signature header is expected in the form:
    |
    |   X-Signature: t=<unix_timestamp>,v1=<hex_hmac_sha256>
    |
    */

    'webhook' => [
        'secret' => env('WEBHOOK_SECRET'),
        'tolerance_seconds' => (int) env('WEBHOOK_TOLERANCE', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Gateway Driver Registry
    |--------------------------------------------------------------------------
    |
    | Maps gateway keys to the concrete driver class and the signature
    | verifier. New gateways can be added here without touching any
    | other code in the codebase.
    |
    */

    'gateways' => [

        'fake' => [
            'driver' => FakePaymentGateway::class,
            'signature_verifier' => FakeSignatureVerifier::class,
        ],

        'mercadopago' => [
            'driver' => MercadoPagoPaymentGateway::class,
            'signature_verifier' => MercadoPagoSignatureVerifier::class,
            'access_token' => env('MP_ACCESS_TOKEN'),
            'api_url' => env('MP_API_URL', 'https://api.mercadopago.com'),
        ],

    ],

];
