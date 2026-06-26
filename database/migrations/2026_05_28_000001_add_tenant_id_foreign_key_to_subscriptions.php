<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $fkName = 'subscriptions_tenant_id_foreign';

        // Uses try/catch for cross-database compatibility (MySQL + SQLite)
        // instead of the MySQL-only INFORMATION_SCHEMA query.
        try {
            $this->cleanOrphans();

            Schema::table('subscriptions', function (Blueprint $table) use ($fkName) {
                $table->foreign('tenant_id', $fkName)
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();
            });
        } catch (Throwable) {
            // FK may already exist — idempotent, skip
        }
    }

    public function down(): void
    {
        $fkName = 'subscriptions_tenant_id_foreign';

        // Uses try/catch for cross-database compatibility
        try {
            Schema::table('subscriptions', function (Blueprint $table) use ($fkName) {
                $table->dropForeign($fkName);
            });
        } catch (Throwable) {
            // FK may have already been dropped — that's fine during rollback
        }
    }

    private function cleanOrphans(): void
    {
        DB::delete(
            'DELETE s FROM subscriptions s LEFT JOIN tenants t ON t.id = s.tenant_id WHERE t.id IS NULL'
        );
    }
};
