<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->uuid('product_id');
            $table->integer('quantity');
            $table->bigInteger('unit_price_cents');
            $table->bigInteger('subtotal_cents');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('order_id')
                ->references('id')->on('orders')
                ->cascadeOnDelete();

            $table->foreign('product_id')
                ->references('id')->on('products')
                ->restrictOnDelete();

            $table->index('order_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
