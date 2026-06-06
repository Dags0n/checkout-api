<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn() => response()->json(['data' => ['status' => 'ok'], 'meta' => []]));

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'idempotency'])->group(function (): void {
    Route::post('/checkout', [CheckoutController::class, 'store']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
});

Route::post('/webhooks/payment', [WebhookController::class, 'payment'])
    ->middleware('verify.webhook.signature');
