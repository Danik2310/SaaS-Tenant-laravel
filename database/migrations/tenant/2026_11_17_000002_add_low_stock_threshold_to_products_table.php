<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional per-product override. When null, the tenant-wide
     * `notifications.low_stock_threshold` setting is used instead.
     */
    public function up(): void
    {
        if (! Schema::hasTable('products') || Schema::hasColumn('products', 'low_stock_threshold')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('low_stock_threshold')->nullable()->after('active');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('products', 'low_stock_threshold')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('low_stock_threshold');
        });
    }
};
