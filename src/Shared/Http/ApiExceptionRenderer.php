<?php

declare(strict_types=1);

namespace Shared\Http;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Shared\Domain\Exceptions\DomainException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class ApiExceptionRenderer
{
    public static function validation(ValidationException $exception): JsonResponse
    {
        return response()->json([
            'data' => null,
            'meta' => [
                'code' => 'VALIDATION_FAILED',
                'message' => 'The given data was invalid.',
                'errors' => $exception->errors(),
            ],
        ], 422);
    }

    public static function domain(DomainException $exception): JsonResponse
    {
        return response()->json([
            'data' => null,
            'meta' => [
                'code' => $exception->errorCode,
                'message' => $exception->getMessage(),
            ],
        ], 422);
    }

    public static function notFound(ModelNotFoundException $exception): JsonResponse
    {
        $model = class_basename($exception->getModel());

        return response()->json([
            'data' => null,
            'meta' => [
                'code' => strtoupper(str_replace('_', '', Str::snake($model))) . '_NOT_FOUND',
                'message' => sprintf('%s does not exist.', ucfirst(strtolower($model))),
            ],
        ], 404);
    }

    public static function notFoundHttp(NotFoundHttpException $exception): JsonResponse
    {
        $previous = $exception->getPrevious();

        if ($previous instanceof ModelNotFoundException) {
            return self::notFound($previous);
        }

        return response()->json([
            'data' => null,
            'meta' => [
                'code' => 'NOT_FOUND',
                'message' => $exception->getMessage() ?: 'Resource not found.',
            ],
        ], 404);
    }

    public static function default(Throwable $exception, Request $request): JsonResponse
    {
        $payload = [
            'data' => null,
            'meta' => [
                'code' => 'INTERNAL_ERROR',
                'message' => 'An unexpected error occurred.',
            ],
        ];

        if (config('app.debug') && $request->is('api/*')) {
            $payload['meta']['debug'] = [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];
        }

        return response()->json($payload, 500);
    }
}
