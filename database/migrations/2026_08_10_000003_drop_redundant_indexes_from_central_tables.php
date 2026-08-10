<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Leftmost-prefix duplicates replaced by wider compound indexes:
        // - subscriptions.status           ⊆ subscriptions[status, ends_at]   (2026_08_10_000002)
        // - permissions.guard_name         ⊆ permissions[guard_name, module, name]
        // - roles.guard_name               ⊆ roles[guard_name, name]
        $prefix = config('database.connections.mysql.prefix', '');

        if (Schema::hasTable('subscriptions') && $this->indexExists($prefix.'subscriptions', 'subscriptions_status_index')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropIndex('subscriptions_status_index');
            });
        }

        if (Schema::hasTable('permissions') && $this->indexExists($prefix.'permissions', 'permissions_guard_name_index')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->dropIndex('permissions_guard_name_index');
            });
        }

        if (Schema::hasTable('roles') && $this->indexExists($prefix.'roles', 'roles_guard_name_index')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropIndex('roles_guard_name_index');
            });
        }
    }

    public function down(): void
    {
        $prefix = config('database.connections.mysql.prefix', '');

        if (Schema::hasTable('subscriptions') && ! $this->indexExists($prefix.'subscriptions', 'subscriptions_status_index')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->index('status', 'subscriptions_status_index');
            });
        }

        if (Schema::hasTable('permissions') && ! $this->indexExists($prefix.'permissions', 'permissions_guard_name_index')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->index('guard_name', 'permissions_guard_name_index');
            });
        }

        if (Schema::hasTable('roles') && ! $this->indexExists($prefix.'roles', 'roles_guard_name_index')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->index('guard_name', 'roles_guard_name_index');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return ! empty(Schema::getConnection()->select(
            "SHOW INDEXES FROM `{$table}` WHERE `Key_name` = ?",
            [$index]
        ));
    }
};
