<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds composite index on [tenant_id, status] for the most common
     * subscription query pattern: "find active/pending/cancelled subscriptions
     * for a given tenant".
     */
    public function up(): void
    {
        // Uses try/catch for cross-database compatibility (MySQL + SQLite)
        // instead of the MySQL-only SHOW INDEX query.
        try {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->index(['tenant_id', 'status']);
            });
        } catch (Throwable) {
            // Index may already exist — idempotent, skip
        }
    }

    /**
     * Reverse the migrations.
     *
     * MySQL InnoDB uses the composite index [tenant_id, status] to enforce
     * the foreign key constraint on tenant_id. We must drop the FK first,
     * then the index, then re-add the FK (which auto-creates a new
     * single-column index for the constraint).
     *
     * NOTE: The FK may have already been dropped by a later migration's
     * down() when rolling back many steps (e.g. 2026_05_28_000001's
     * down() drops it first). Uses try/catch guard instead of the
     * MySQL-only INFORMATION_SCHEMA query to avoid errors.
     */
    public function down(): void
    {
        $fkName = 'subscriptions_tenant_id_foreign';

        // Only drop the FK if it still exists (a later migration's
        // down() may have already dropped it during a full rollback).
        // Uses try/catch for cross-database compatibility.
        try {
            Schema::table('subscriptions', function (Blueprint $table) use ($fkName) {
                $table->dropForeign($fkName);
            });
        } catch (Throwable) {
            // FK may have already been dropped — that's fine
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'status']);
        });

        // Always re-add the FK regardless — if it was already dropped by
        // a later migration, we need to restore it for the next earlier
        // migration's down() that may expect it to exist.
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
        });
    }
};
