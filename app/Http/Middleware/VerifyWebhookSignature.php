<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Payment\Domain\Contracts\WebhookSignatureVerifierContract;
use Payment\Domain\ValueObjects\WebhookSignatureContext;
use Symfony\Component\HttpFoundation\Response;

final class VerifyWebhookSignature
{
    public function __construct(private readonly WebhookSignatureVerifierContract $verifier) {}

    public function handle(Request $request, Closure $next): Response
    {
        $signatureHeader = (string) $request->header('X-Signature', '');
        if ($signatureHeader === '') {
            return $this->unauthorized('Missing signature headers.');
        }

        $parts = $this->parseSignature($signatureHeader);
        if ($parts === null) {
            return $this->unauthorized('Malformed signature header.');
        }

        $hmac = $parts['v1'] ?? '';
        if ($hmac === '') {
            return $this->unauthorized('Missing v1 component.');
        }

        $timestamp = (int) ($parts['ts'] ?? $request->header('X-Signature-Timestamp', 0));
        if ($timestamp === 0) {
            return $this->unauthorized('Missing signature timestamp.');
        }

        $context = new WebhookSignatureContext(
            rawBody: (string) $request->getContent(),
            timestamp: $timestamp,
            signature: $hmac,
            dataId: $this->extractDataId($request),
            requestId: $request->header('X-Request-Id'),
        );

        if (! $this->verifier->verify($context)) {
            return $this->unauthorized('Invalid signature or expired timestamp.');
        }

        return $next($request);
    }

    private function parseSignature(string $header): ?array
    {
        $result = [];
        foreach (explode(',', $header) as $segment) {
            $kv = explode('=', trim($segment), 2);
            if (count($kv) === 2) {
                $result[trim($kv[0])] = trim($kv[1]);
            }
        }

        return $result === [] ? null : $result;
    }

    private function extractDataId(Request $request): ?string
    {
        $dataId = $request->query('data_id') ?? $request->query('id');

        return is_string($dataId) ? $dataId : null;
    }

    private function unauthorized(string $message): JsonResponse
    {
        return response()->json([
            'data' => null,
            'meta' => [
                'code' => 'INVALID_SIGNATURE',
                'message' => $message,
            ],
        ], 401);
    }
}
