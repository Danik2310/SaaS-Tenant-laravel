<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'unit_price')) {
                DB::statement('ALTER TABLE order_items ADD COLUMN unit_price DECIMAL(12,2) DEFAULT NULL AFTER price');
            }

            if (! Schema::hasColumn('order_items', 'discount')) {
                DB::statement('ALTER TABLE order_items ADD COLUMN discount DECIMAL(12,2) DEFAULT 0.00 AFTER unit_price');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'notes')) {
                DB::statement('ALTER TABLE payments ADD COLUMN notes TEXT DEFAULT NULL AFTER reference');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'discount')) {
                DB::statement('ALTER TABLE order_items DROP COLUMN discount');
            }

            if (Schema::hasColumn('order_items', 'unit_price')) {
                DB::statement('ALTER TABLE order_items DROP COLUMN unit_price');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'notes')) {
                DB::statement('ALTER TABLE payments DROP COLUMN notes');
            }
        });
    }
};
