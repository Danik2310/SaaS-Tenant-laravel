<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add composite indexes matching the payment-history queries:
     * - tenant history filters by tenant and sorts by paid_at
     * - admin history filters by subscription and sorts by paid_at
     */
    public function up(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->index(['tenant_id', 'paid_at']);
            $table->index(['subscription_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->dropForeign(['subscription_id']);
            $table->dropForeign(['tenant_id']);

            $table->dropIndex(['tenant_id', 'paid_at']);
            $table->dropIndex(['subscription_id', 'paid_at']);

            $table->foreign('subscription_id')->references('id')->on('subscriptions')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }
};
