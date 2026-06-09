<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('activitylog.table_name');

        if (Schema::hasColumn($table, 'subject_id')) {
            DB::statement("ALTER TABLE {$table} MODIFY subject_id VARCHAR(64) NULL");
        }
        if (Schema::hasColumn($table, 'causer_id')) {
            DB::statement("ALTER TABLE {$table} MODIFY causer_id VARCHAR(64) NULL");
        }
    }

    public function down(): void
    {
        $table = config('activitylog.table_name');

        if (Schema::hasColumn($table, 'subject_id')) {
            DB::statement("ALTER TABLE {$table} MODIFY subject_id BIGINT UNSIGNED NULL");
        }
        if (Schema::hasColumn($table, 'causer_id')) {
            DB::statement("ALTER TABLE {$table} MODIFY causer_id BIGINT UNSIGNED NULL");
        }
    }
};
