<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $fkName = $this->getForeignKeyName('subscriptions', 'tenant_id');

        if (! $this->foreignKeyExists('subscriptions', $fkName)) {
            $this->cleanOrphans();

            Schema::table('subscriptions', function (Blueprint $table) use ($fkName) {
                $table->foreign('tenant_id', $fkName)
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        $fkName = $this->getForeignKeyName('subscriptions', 'tenant_id');

        if ($this->foreignKeyExists('subscriptions', $fkName)) {
            Schema::table('subscriptions', function (Blueprint $table) use ($fkName) {
                $table->dropForeign($fkName);
            });
        }
    }

    private function cleanOrphans(): void
    {
        DB::delete(
            'DELETE s FROM subscriptions s LEFT JOIN tenants t ON t.id = s.tenant_id WHERE t.id IS NULL'
        );
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

    private function getForeignKeyName(string $table, string $column): string
    {
        return $table.'_'.$column.'_foreign';
    }
};
