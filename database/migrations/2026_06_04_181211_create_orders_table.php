<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('status', 16);
            $table->bigInteger('total_cents');
            $table->string('currency', 3)->default('BRL');
            $table->timestamps();

            $table->index('status');
            $table->index('customer_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
