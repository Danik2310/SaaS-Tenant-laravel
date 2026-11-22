<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stripe subscription items for Cashier, including metered-price columns.
     */
    public function up(): void
    {
        if (Schema::hasTable('cashier_subscription_items')) {
            return;
        }

        Schema::create('cashier_subscription_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_id');
            $table->string('stripe_id', 255)->unique()->collation('utf8mb4_bin');
            $table->string('stripe_product', 255);
            $table->string('stripe_price', 255);
            $table->string('meter_id', 255)->nullable();
            $table->integer('quantity')->nullable();
            $table->string('meter_event_name', 255)->nullable();
            $table->timestamps();

            $table->index(['subscription_id', 'stripe_price'], 'cashier_sub_items_sub_price_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashier_subscription_items');
    }
};
