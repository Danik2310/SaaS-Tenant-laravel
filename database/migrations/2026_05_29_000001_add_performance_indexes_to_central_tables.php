<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function indexExists(string $table, string $index): bool
    {
        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]);

        return ! empty($indexes);
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        $database = DB::connection()->getDatabaseName();
        $result = DB::select(
            'SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$database, $table, $constraint, 'FOREIGN KEY']
        );

        return ! empty($result);
    }

    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! $this->indexExists('tenants', 'tenants_plan_id_status_index')) {
                $table->index(['plan_id', 'status']);
            }
        });

        Schema::table('domains', function (Blueprint $table) {
            if (! $this->indexExists('domains', 'domains_tenant_id_index')) {
                $table->index('tenant_id');
            }
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            if (! $this->indexExists('subscriptions', 'subscriptions_plan_id_status_index')) {
                $table->index(['plan_id', 'status']);
            }
        });

        Schema::table('payment_methods', function (Blueprint $table) {
            if (! $this->indexExists('payment_methods', 'payment_methods_active_index')) {
                $table->index('active');
            }
        });
    }

    public function down(): void
    {
        // 🔥 MySQL limitation: You cannot drop an index that a FOREIGN KEY constraint
        // depends on. The composite index `tenants_plan_id_status_index (plan_id, status)`
        // includes `plan_id` which has FK `tenants_plan_id_foreign` → plans(id). MySQL
        // internally links this FK to the composite index. The ONLY way to drop it is
        // to first drop the FK constraint, then drop the index.
        //
        // The FK tenants_plan_id_foreign was added by `2026_05_07_000001_add_plan_id_to_tenants_table`.
        // Its down() already guards FK existence before dropping, so the double-drop
        // is safe when rolling back many steps.
        if ($this->foreignKeyExists('tenants', 'tenants_plan_id_foreign')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropForeign('tenants_plan_id_foreign');
            });
        }

        Schema::table('tenants', function (Blueprint $table) {
            if ($this->indexExists('tenants', 'tenants_plan_id_status_index')) {
                $table->dropIndex(['plan_id', 'status']);
            }
        });

        // domains.tenant_id has FK domains_tenant_id_foreign → tenants(id)
        // Must drop FK before the index (MySQL restriction).
        if ($this->foreignKeyExists('domains', 'domains_tenant_id_foreign')) {
            Schema::table('domains', function (Blueprint $table) {
                $table->dropForeign('domains_tenant_id_foreign');
            });
        }

        Schema::table('domains', function (Blueprint $table) {
            if ($this->indexExists('domains', 'domains_tenant_id_index')) {
                $table->dropIndex(['tenant_id']);
            }
        });

        // Same issue for subscriptions_plan_id_status_index — plan_id has FK
        // subscriptions_plan_id_foreign → plans(id). The subscriptions FK migration
        // (2026_05_08_000003) already guards FK existence before dropping.
        if ($this->foreignKeyExists('subscriptions', 'subscriptions_plan_id_foreign')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropForeign('subscriptions_plan_id_foreign');
            });
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            if ($this->indexExists('subscriptions', 'subscriptions_plan_id_status_index')) {
                $table->dropIndex(['plan_id', 'status']);
            }
        });

        Schema::table('payment_methods', function (Blueprint $table) {
            if ($this->indexExists('payment_methods', 'payment_methods_active_index')) {
                $table->dropIndex(['active']);
            }
        });
    }
};
