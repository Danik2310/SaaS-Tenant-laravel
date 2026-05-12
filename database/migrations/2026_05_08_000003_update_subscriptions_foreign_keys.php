<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop foreign key if it exists (query INFORMATION_SCHEMA instead of Doctrine)
        $fkName = $this->getForeignKeyName('subscriptions', 'plan_id');
        if ($this->foreignKeyExists('subscriptions', $fkName)) {
            Schema::table('subscriptions', function (Blueprint $table) use ($fkName) {
                $table->dropForeign($fkName);
            });
        }

        // Make plan_id nullable — use raw SQL to avoid Doctrine dependency
        DB::statement('ALTER TABLE subscriptions MODIFY COLUMN plan_id BIGINT UNSIGNED NULL');

        // Re-add FK with nullOnDelete
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreign('plan_id')
                ->references('id')
                ->on('plans')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Drop the new FK
        $fkName = $this->getForeignKeyName('subscriptions', 'plan_id');
        if ($this->foreignKeyExists('subscriptions', $fkName)) {
            Schema::table('subscriptions', function (Blueprint $table) use ($fkName) {
                $table->dropForeign($fkName);
            });
        }

        // Only revert to NOT NULL if no null values exist (safety check)
        $hasNulls = DB::table('subscriptions')->whereNull('plan_id')->exists();
        if (! $hasNulls) {
            DB::statement('ALTER TABLE subscriptions MODIFY COLUMN plan_id BIGINT UNSIGNED NOT NULL');
        }

        // Re-add the original FK
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreign('plan_id')
                ->references('id')
                ->on('plans')
                ->cascadeOnDelete();
        });
    }

    /**
     * Check if a foreign key exists using INFORMATION_SCHEMA.
     */
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
     * Get the default Laravel foreign key name for a column.
     */
    private function getForeignKeyName(string $table, string $column): string
    {
        return $table.'_'.$column.'_foreign';
    }
};
