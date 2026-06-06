<?php

declare(strict_types=1);

namespace App\Providers;

use App\Policies\OrderPolicy;
use Catalog\Domain\Contracts\ProductRepositoryContract;
use Catalog\Infrastructure\EloquentProductRepository;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Order\Domain\Contracts\OrderRepositoryContract;
use Order\Domain\Order;
use Order\Infrastructure\EloquentOrderRepository;
use Payment\Domain\Contracts\PaymentGatewayContract;
use Payment\Domain\Contracts\PaymentRepositoryContract;
use Payment\Domain\Contracts\WebhookSignatureVerifierContract;
use Payment\Infrastructure\Persistence\EloquentPaymentRepository;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerRepositories();
        $this->registerGatewayBindings();
    }

    public function boot(): void
    {
        Gate::policy(Order::class, OrderPolicy::class);
    }

    private function registerRepositories(): void
    {
        $this->app->bind(ProductRepositoryContract::class, EloquentProductRepository::class);
        $this->app->bind(OrderRepositoryContract::class, EloquentOrderRepository::class);
        $this->app->bind(PaymentRepositoryContract::class, EloquentPaymentRepository::class);
    }

    private function registerGatewayBindings(): void
    {
        $gatewayKey = config('payment.gateway');
        $gatewayConfig = config("payment.gateways.{$gatewayKey}");

        if (! is_array($gatewayConfig)) {
            throw new \RuntimeException(
                "Unknown payment gateway '{$gatewayKey}'. Check the PAYMENT_GATEWAY env value."
            );
        }

        $this->app->bind(PaymentGatewayContract::class, $gatewayConfig['driver']);
        $this->app->bind(WebhookSignatureVerifierContract::class, $gatewayConfig['signature_verifier']);
    }
}
