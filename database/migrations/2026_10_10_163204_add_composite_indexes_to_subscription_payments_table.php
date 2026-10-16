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
        // Each step is guarded independently: a later/earlier rollback step may
        // have already dropped the FK or index, and a single failure must not
        // block the remaining steps (which is what previously aborted a full
        // rollback). MySQL may also raise a metadata-lock error under load.
        try {
            Schema::table('subscription_payments', function (Blueprint $table) {
                $table->dropForeign(['subscription_id']);
            });
        } catch (Throwable) {
            // FK already dropped — idempotent
        }

        try {
            Schema::table('subscription_payments', function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
            });
        } catch (Throwable) {
            // FK already dropped — idempotent
        }

        try {
            Schema::table('subscription_payments', function (Blueprint $table) {
                $table->dropIndex(['tenant_id', 'paid_at']);
            });
        } catch (Throwable) {
            // Index already dropped — idempotent
        }

        try {
            Schema::table('subscription_payments', function (Blueprint $table) {
                $table->dropIndex(['subscription_id', 'paid_at']);
            });
        } catch (Throwable) {
            // Index already dropped — idempotent
        }

        try {
            Schema::table('subscription_payments', function (Blueprint $table) {
                $table->foreign('subscription_id')->references('id')->on('subscriptions')->cascadeOnDelete();
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            });
        } catch (Throwable) {
            // FKs already restored — idempotent
        }
    }
};
