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
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['plan_id', 'status']);
        });

        Schema::table('domains', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['plan_id', 'status']);
        });

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropIndex(['active']);
        });
    }
};
