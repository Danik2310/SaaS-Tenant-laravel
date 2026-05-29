<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'status')) {
            DB::statement("ALTER TABLE orders MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pending'");
        }

        if (Schema::hasTable('inventory_movements') && Schema::hasColumn('inventory_movements', 'type')) {
            DB::statement('ALTER TABLE inventory_movements MODIFY COLUMN type VARCHAR(20) NOT NULL');
        }

        if (Schema::hasTable('order_items') && Schema::hasColumn('order_items', 'quantity')) {
            DB::statement('ALTER TABLE order_items MODIFY COLUMN quantity INT UNSIGNED NOT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'status')) {
            DB::statement("ALTER TABLE orders MODIFY COLUMN status VARCHAR(255) NOT NULL DEFAULT 'pending'");
        }

        if (Schema::hasTable('inventory_movements') && Schema::hasColumn('inventory_movements', 'type')) {
            DB::statement('ALTER TABLE inventory_movements MODIFY COLUMN type VARCHAR(255) NOT NULL');
        }

        if (Schema::hasTable('order_items') && Schema::hasColumn('order_items', 'quantity')) {
            DB::statement('ALTER TABLE order_items MODIFY COLUMN quantity INT NOT NULL');
        }
    }
};
