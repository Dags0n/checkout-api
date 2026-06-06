<?php

declare(strict_types=1);

use App\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Shared\Domain\Exceptions\DomainException;
use Shared\Http\ApiExceptionRenderer;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'verify.webhook.signature' => VerifyWebhookSignature::class,
            'auth' => \App\Http\Middleware\Authenticate::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (ValidationException $e) {
            return ApiExceptionRenderer::validation($e);
        });

        $exceptions->render(function (DomainException $e) {
            return ApiExceptionRenderer::domain($e);
        });

        $exceptions->render(function (ModelNotFoundException $e) {
            return ApiExceptionRenderer::notFound($e);
        });

        $exceptions->render(function (NotFoundHttpException $e) {
            return ApiExceptionRenderer::notFoundHttp($e);
        });
    })->create();
