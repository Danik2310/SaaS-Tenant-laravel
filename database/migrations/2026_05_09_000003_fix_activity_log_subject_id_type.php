<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('activitylog.table_name');

        DB::statement("ALTER TABLE {$table} MODIFY subject_id VARCHAR(64) NULL");
        DB::statement("ALTER TABLE {$table} MODIFY causer_id VARCHAR(64) NULL");
    }

    public function down(): void
    {
        $table = config('activitylog.table_name');

        DB::statement("ALTER TABLE {$table} MODIFY subject_id BIGINT UNSIGNED NULL");
        DB::statement("ALTER TABLE {$table} MODIFY causer_id BIGINT UNSIGNED NULL");
    }
};
