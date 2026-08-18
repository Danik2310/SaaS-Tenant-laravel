<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Heal central-DB referential integrity after a partial rollback of
     * 2026_05_29_000001_add_performance_indexes_to_central_tables, whose old
     * down() dropped the FK constraints below (to release the composite
     * indexes that MySQL InnoDB attaches them to) without re-adding them.
     *
     * Idempotent: each FK is only re-added when it is missing AND its column
     * still exists, so it is safe on a fresh database, on a database that was
     * never rolled back, and on one where a column was later removed.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $existingConstraints = collect(
            DB::select(
                "SELECT CONSTRAINT_NAME
                 FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME IN ('tenants', 'domains', 'subscriptions')
                   AND CONSTRAINT_NAME IN ('tenants_plan_id_foreign', 'domains_tenant_id_foreign', 'subscriptions_plan_id_foreign')
                   AND REFERENCED_TABLE_NAME IS NOT NULL"
            )
        )->pluck('CONSTRAINT_NAME')->flip();

        $this->restoreForeign('tenants', 'plan_id', 'plans', 'tenants_plan_id_foreign', 'nullOnDelete', $existingConstraints);
        $this->restoreForeign('domains', 'tenant_id', 'tenants', 'domains_tenant_id_foreign', 'cascadeOnDelete', $existingConstraints);
        $this->restoreForeign('subscriptions', 'plan_id', 'plans', 'subscriptions_plan_id_foreign', 'nullOnDelete', $existingConstraints);
    }

    /**
     * Re-add a missing FK constraint (with its auto-generated index) when the
     * owning column is still present.
     */
    protected function restoreForeign(
        string $table,
        string $column,
        string $references,
        string $constraintName,
        string $onDelete,
        Collection $existingConstraints
    ): void {
        if ($existingConstraints->has($constraintName)) {
            return;
        }

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $references, $onDelete) {
            $blueprint->foreign($column)
                ->references('id')
                ->on($references)
                ->{$onDelete}();
        });
    }

    public function down(): void
    {
        // Healing migration — nothing to undo; the FKs are restored by the
        // owning migrations' down() paths during a full rollback.
    }
};
