<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Uses try/catch for cross-database compatibility (MySQL + SQLite)
        // instead of the MySQL-only SHOW INDEX query.

        try {
            Schema::table('tenants', function (Blueprint $table) {
                $table->index(['plan_id', 'status']);
            });
        } catch (Throwable) {
            // Index may already exist — idempotent, skip
        }

        try {
            Schema::table('domains', function (Blueprint $table) {
                $table->index('tenant_id');
            });
        } catch (Throwable) {
            // Index may already exist — idempotent, skip
        }

        try {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->index(['plan_id', 'status']);
            });
        } catch (Throwable) {
            // Index may already exist — idempotent, skip
        }

        try {
            Schema::table('payment_methods', function (Blueprint $table) {
                $table->index('active');
            });
        } catch (Throwable) {
            // Index may already exist — idempotent, skip
        }
    }

    public function down(): void
    {
        // 🔥 MySQL limitation: You cannot drop an index that a FOREIGN KEY constraint
        // depends on. The composite index `tenants_plan_id_status_index (plan_id, status)`
        // includes `plan_id` which has FK `tenants_plan_id_foreign` → plans(id). MySQL
        // internally links this FK to the composite index. The ONLY way to drop it is
        // to first drop the FK constraint, then drop the index.
        //
        // The FK tenants_plan_id_foreign was added by `2026_05_07_000001_add_plan_id_to_tenants_table`.
        // Its down() already guards FK existence before dropping, so the double-drop
        // is safe when rolling back many steps.
        //
        // All FK/index existence checks use try/catch for cross-database compatibility
        // (MySQL + SQLite) instead of MySQL-only INFORMATION_SCHEMA / SHOW INDEX queries.

        // Drop FK for tenants.plan_id first (before index that MySQL depends on it)
        try {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropForeign('tenants_plan_id_foreign');
            });
        } catch (Throwable) {
            // FK may have already been dropped — that's fine during rollback
        }

        try {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropIndex(['plan_id', 'status']);
            });
        } catch (Throwable) {
            // Index may have already been dropped — that's fine
        }

        // Drop FK for domains.tenant_id first (before index that MySQL depends on it)
        try {
            Schema::table('domains', function (Blueprint $table) {
                $table->dropForeign('domains_tenant_id_foreign');
            });
        } catch (Throwable) {
            // FK may have already been dropped — that's fine during rollback
        }

        try {
            Schema::table('domains', function (Blueprint $table) {
                $table->dropIndex(['tenant_id']);
            });
        } catch (Throwable) {
            // Index may have already been dropped — that's fine
        }

        // Same issue for subscriptions_plan_id_status_index — plan_id has FK
        // subscriptions_plan_id_foreign → plans(id). The subscriptions FK migration
        // (2026_05_08_000003) already guards FK existence before dropping.
        try {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropForeign('subscriptions_plan_id_foreign');
            });
        } catch (Throwable) {
            // FK may have already been dropped — that's fine during rollback
        }

        try {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropIndex(['plan_id', 'status']);
            });
        } catch (Throwable) {
            // Index may have already been dropped — that's fine
        }

        try {
            Schema::table('payment_methods', function (Blueprint $table) {
                $table->dropIndex(['active']);
            });
        } catch (Throwable) {
            // Index may have already been dropped — that's fine
        }
    }
};
