<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenants') && Schema::hasColumn('tenants', 'status')) {
            DB::statement("ALTER TABLE tenants MODIFY COLUMN status VARCHAR(20) DEFAULT 'Active'");
        }

        if (Schema::hasTable('payment_methods') && Schema::hasColumn('payment_methods', 'provider')) {
            DB::statement('ALTER TABLE payment_methods MODIFY COLUMN provider VARCHAR(30) DEFAULT NULL');
        }

        if (Schema::hasTable('payment_methods') && Schema::hasColumn('payment_methods', 'mode')) {
            DB::statement("ALTER TABLE payment_methods MODIFY COLUMN mode VARCHAR(10) DEFAULT 'test'");
        }

        if (Schema::hasTable('subscriptions') && Schema::hasColumn('subscriptions', 'status')) {
            DB::statement("ALTER TABLE subscriptions MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active'");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tenants') && Schema::hasColumn('tenants', 'status')) {
            DB::statement("ALTER TABLE tenants MODIFY COLUMN status VARCHAR(255) DEFAULT 'Active'");
        }

        if (Schema::hasTable('payment_methods') && Schema::hasColumn('payment_methods', 'provider')) {
            DB::statement('ALTER TABLE payment_methods MODIFY COLUMN provider VARCHAR(255) DEFAULT NULL');
        }

        if (Schema::hasTable('payment_methods') && Schema::hasColumn('payment_methods', 'mode')) {
            DB::statement("ALTER TABLE payment_methods MODIFY COLUMN mode VARCHAR(255) DEFAULT 'test'");
        }

        if (Schema::hasTable('subscriptions') && Schema::hasColumn('subscriptions', 'status')) {
            DB::statement("ALTER TABLE subscriptions MODIFY COLUMN status VARCHAR(255) NOT NULL DEFAULT 'active'");
        }
    }
};
