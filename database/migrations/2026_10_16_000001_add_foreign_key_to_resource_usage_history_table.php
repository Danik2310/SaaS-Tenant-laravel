<?php

use App\Shared\Traits\HandlesIdempotentSchemaChanges;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HandlesIdempotentSchemaChanges;

    private string $fkName = 'resource_usage_history_tenant_id_foreign';

    public function up(): void
    {
        // Guarded for cross-database compatibility (MySQL + SQLite) and so a
        // re-run against an already-constrained schema is a no-op.
        try {
            $this->cleanOrphans();

            Schema::table('resource_usage_history', function (Blueprint $table) {
                $table->foreign('tenant_id', $this->fkName)
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();
            });
        } catch (Throwable $e) {
            if (! $this->isBenignSchemaError($e)) {
                throw $e;
            }
            // FK may already exist — idempotent, skip
        }
    }

    public function down(): void
    {
        try {
            Schema::table('resource_usage_history', function (Blueprint $table) {
                $table->dropForeign($this->fkName);
            });
        } catch (Throwable $e) {
            if (! $this->isBenignSchemaError($e)) {
                throw $e;
            }
            // FK may have already been dropped — that's fine during rollback
        }
    }

    /**
     * Remove rows that reference a tenant that no longer exists so the FK
     * constraint can be added without violating referential integrity.
     */
    private function cleanOrphans(): void
    {
        DB::delete(
            'DELETE h FROM resource_usage_history h LEFT JOIN tenants t ON t.id = h.tenant_id WHERE t.id IS NULL'
        );
    }
};
