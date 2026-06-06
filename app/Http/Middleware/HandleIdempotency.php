<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class HandleIdempotency
{
    public function handle(Request $request, Closure $next, int $ttlSeconds = 900): Response
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return $next($request);
        }

        $key = $request->header('X-Idempotency-Key');

        if (! $key) {
            return $next($request);
        }

        $cacheKey = "idempotency:{$request->user()?->id}:{$key}";

        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return response()->json($cached['body'], $cached['status']);
        }

        $response = $next($request);

        if ($response->isSuccessful() || $response->getStatusCode() >= 500) {
            Cache::put($cacheKey, [
                'body'   => json_decode($response->getContent(), true),
                'status' => $response->getStatusCode(),
            ], $ttlSeconds);
        }

        return $response;
    }
}
