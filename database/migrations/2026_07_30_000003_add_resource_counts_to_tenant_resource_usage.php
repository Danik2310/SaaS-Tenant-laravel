<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_resource_usage', function (Blueprint $table) {
            if (! Schema::hasColumn('tenant_resource_usage', 'warehouses_count')) {
                $table->integer('warehouses_count')->default(0)->after('orders_count');
            }
            if (! Schema::hasColumn('tenant_resource_usage', 'categories_count')) {
                $table->integer('categories_count')->default(0)->after('warehouses_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenant_resource_usage', function (Blueprint $table) {
            $table->dropColumn(['warehouses_count', 'categories_count']);
        });
    }
};
