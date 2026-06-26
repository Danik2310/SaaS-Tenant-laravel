<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $fkName = 'subscriptions_plan_id_foreign';

        // Drop foreign key if it exists — uses try/catch for cross-database
        // compatibility (MySQL + SQLite) instead of INFORMATION_SCHEMA.
        try {
            Schema::table('subscriptions', function (Blueprint $table) use ($fkName) {
                $table->dropForeign($fkName);
            });
        } catch (Throwable) {
            // FK may not exist — proceed with the rest of the migration
        }

        // Make plan_id nullable (MySQL only — SQLite and others handle this
        // via the column definition in the original create migration).
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE subscriptions MODIFY COLUMN plan_id BIGINT UNSIGNED NULL');
        }

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
        $fkName = 'subscriptions_plan_id_foreign';

        // Drop the new FK — uses try/catch for cross-database compatibility
        try {
            Schema::table('subscriptions', function (Blueprint $table) use ($fkName) {
                $table->dropForeign($fkName);
            });
        } catch (Throwable) {
            // FK may have already been dropped — that's fine during rollback
        }

        // Only revert to NOT NULL if no null values exist (safety check)
        $hasNulls = DB::table('subscriptions')->whereNull('plan_id')->exists();
        if (! $hasNulls) {
            if (DB::connection()->getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE subscriptions MODIFY COLUMN plan_id BIGINT UNSIGNED NOT NULL');
            }
        }

        // Re-add the original FK
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreign('plan_id')
                ->references('id')
                ->on('plans')
                ->cascadeOnDelete();
        });
    }
};
