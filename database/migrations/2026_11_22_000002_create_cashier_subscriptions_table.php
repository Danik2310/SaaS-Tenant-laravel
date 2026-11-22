<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cashier-owned Stripe subscription state.
     *
     * Kept separate from the existing central `subscriptions` table, which
     * remains the local plan/entitlement projection. The billable key follows
     * Cashier's `BillingCustomer::getForeignKey()` convention.
     */
    public function up(): void
    {
        if (Schema::hasTable('cashier_subscriptions')) {
            return;
        }

        Schema::create('cashier_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('billing_customer_id', 255)->index();
            $table->string('type', 255);
            $table->string('stripe_id', 255)->unique()->collation('utf8mb4_bin');
            $table->string('stripe_status', 255);
            $table->string('stripe_price', 255)->nullable();
            $table->integer('quantity')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['billing_customer_id', 'stripe_status'], 'cashier_subs_customer_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashier_subscriptions');
    }
};
