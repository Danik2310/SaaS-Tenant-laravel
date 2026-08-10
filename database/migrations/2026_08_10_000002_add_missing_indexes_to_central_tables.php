<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // activity_log.created_at — ActivityLogController filters and orders by
        // created_at (whereDate + orderBy desc); the table only had log_name.
        $logTable = config('activitylog.table_name', 'activity_log');

        if (Schema::hasTable($logTable)
            && Schema::hasColumn($logTable, 'created_at')
            && ! Schema::hasIndex($logTable, 'activity_log_created_at_index')
        ) {
            Schema::table($logTable, function (Blueprint $table) {
                $table->index('created_at');
            });
        }

        // subscriptions [status, ends_at] — Subscription::scopeActive() and
        // scopeExpired() filter by both columns. Expand step: the old
        // subscriptions_status_index is dropped by a follow-up migration.
        if (Schema::hasTable('subscriptions')
            && Schema::hasColumn('subscriptions', 'status')
            && Schema::hasColumn('subscriptions', 'ends_at')
            && ! Schema::hasIndex('subscriptions', 'subscriptions_status_ends_at_index')
        ) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->index(['status', 'ends_at']);
            });
        }
    }

    public function down(): void
    {
        $logTable = config('activitylog.table_name', 'activity_log');

        if (Schema::hasTable($logTable) && Schema::hasIndex($logTable, 'activity_log_created_at_index')) {
            Schema::table($logTable, function (Blueprint $table) {
                $table->dropIndex('activity_log_created_at_index');
            });
        }

        if (Schema::hasTable('subscriptions') && Schema::hasIndex('subscriptions', 'subscriptions_status_ends_at_index')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropIndex('subscriptions_status_ends_at_index');
            });
        }
    }
};
