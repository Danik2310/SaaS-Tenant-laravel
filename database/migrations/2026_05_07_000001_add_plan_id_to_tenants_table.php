<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'plan_id')) {
                $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete()->after('status');
            }
            if (! Schema::hasColumn('tenants', 'trial_ends_at')) {
                $table->timestamp('trial_ends_at')->nullable()->after('plan_id');
            }
        });
    }

    public function down(): void
    {
        // 🔥 Guard: the FK may have already been dropped by
        // 2026_05_29_000001_add_performance_indexes_to_central_tables::down()
        // which needs to drop the FK before removing a composite index that
        // includes the FK column (MySQL limitation).
        //
        // Uses try/catch for cross-database compatibility (MySQL + SQLite)
        // instead of the MySQL-only INFORMATION_SCHEMA query.
        try {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropForeign(['plan_id']);
            });
        } catch (Throwable) {
            // FK may have already been dropped — that's fine during rollback
        }

        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'plan_id')) {
                $table->dropColumn('plan_id');
            }
            if (Schema::hasColumn('tenants', 'trial_ends_at')) {
                $table->dropColumn('trial_ends_at');
            }
        });
    }
};
