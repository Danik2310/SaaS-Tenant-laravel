<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->foreignKeyExists('tenant_resource_usage', 'tenant_resource_usage_tenant_id_foreign')) {
            Schema::table('tenant_resource_usage', function (Blueprint $table) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        if ($this->foreignKeyExists('tenant_resource_usage', 'tenant_resource_usage_tenant_id_foreign')) {
            Schema::table('tenant_resource_usage', function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
            });
        }
    }

    private function foreignKeyExists(string $table, string $foreignKeyName): bool
    {
        $constraints = DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$table, $foreignKeyName]
        );

        return ! empty($constraints);
    }
};
