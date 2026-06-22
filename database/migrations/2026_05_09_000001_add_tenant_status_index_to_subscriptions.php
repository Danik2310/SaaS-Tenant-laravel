<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        Schema::table('subscriptions', function (Blueprint $table) {
            if (! $this->indexExists('subscriptions', 'subscriptions_tenant_id_status_index')) {
                $table->index(['tenant_id', 'status']);
            }
        });
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
     * down() drops it first). Guard with foreignKeyExists() to avoid
     * MySQL error 1091 "Can't DROP ... check that column/key exists".
     */
    public function down(): void
    {
        $fkName = $this->getForeignKeyName('subscriptions', 'tenant_id');

        $needToRecreateFk = $this->foreignKeyExists('subscriptions', $fkName);

        if ($needToRecreateFk) {
            // Only drop the FK if it still exists (a later migration's
            // down() may have already dropped it during a full rollback).
            Schema::table('subscriptions', function (Blueprint $table) use ($fkName) {
                $table->dropForeign($fkName);
            });
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

    /**
     * Check if a foreign key constraint exists in the database.
     *
     * MySQL INFORMATION_SCHEMA.TABLE_CONSTRAINTS may report stale metadata
     * in rare cases (information_schema_stats_expiry). This method uses a
     * direct INFORMATION_SCHEMA query to check FK existence.
     */
    private function indexExists(string $table, string $index): bool
    {
        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]);

        return ! empty($indexes);
    }

    private function foreignKeyExists(string $table, string $indexName): bool
    {
        $database = DB::connection()->getDatabaseName();
        $result = DB::select(
            'SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$database, $table, $indexName, 'FOREIGN KEY']
        );

        return ! empty($result);
    }

    /**
     * Get the foreign key name for a column on a table.
     * Mirrors Illuminate\Database\Schema\Blueprint::getForeignKeyName().
     */
    private function getForeignKeyName(string $table, string $column): string
    {
        $parts = explode('.', $table);
        $tableName = end($parts);

        return $tableName.'_'.$column.'_foreign';
    }
};
