<?php

declare(strict_types=1);

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
        Schema::table('inventory_movements', function (Blueprint $table) {
            if (! $this->indexExists('inventory_movements', 'inventory_movements_warehouse_id_index')) {
                $table->index('warehouse_id');
            }

            if (! $this->indexExists('inventory_movements', 'inventory_movements_product_id_created_at_index')) {
                $table->index(['product_id', 'created_at']);
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (! $this->indexExists('order_items', 'order_items_order_id_product_id_index')) {
                $table->index(['order_id', 'product_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            if ($this->indexExists('inventory_movements', 'inventory_movements_warehouse_id_index')) {
                $table->dropIndex(['warehouse_id']);
            }
            if ($this->indexExists('inventory_movements', 'inventory_movements_product_id_created_at_index')) {
                $table->dropIndex(['product_id', 'created_at']);
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if ($this->indexExists('order_items', 'order_items_order_id_product_id_index')) {
                $table->dropIndex(['order_id', 'product_id']);
            }
        });
    }
};
