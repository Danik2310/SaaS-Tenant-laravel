<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds missing indexes identified by migration audit:
     * - orders: status, [customer_id, status]
     * - customers: phone, document_number, active
     * - inventory_movements: [product_id, type]
     * - payments: reference
     */
    public function up(): void
    {
        // Orders
        Schema::table('orders', function (Blueprint $table) {
            if (! $this->indexExists('orders', 'orders_status_index')) {
                $table->index('status');
            }
            if (! $this->indexExists('orders', 'orders_customer_id_status_index')) {
                $table->index(['customer_id', 'status']);
            }
        });

        // Customers
        Schema::table('customers', function (Blueprint $table) {
            if (! $this->indexExists('customers', 'customers_phone_index')) {
                $table->index('phone');
            }
            if (! $this->indexExists('customers', 'customers_document_index')) {
                $table->index('document');
            }
            if (! $this->indexExists('customers', 'customers_active_index')) {
                $table->index('active');
            }
        });

        // Inventory movements
        Schema::table('inventory_movements', function (Blueprint $table) {
            if (! $this->indexExists('inventory_movements', 'inventory_movements_product_id_type_index')) {
                $table->index(['product_id', 'type']);
            }
        });

        // Payments
        Schema::table('payments', function (Blueprint $table) {
            if (! $this->indexExists('payments', 'payments_reference_index')) {
                $table->index('reference');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if ($this->indexExists('orders', 'orders_status_index')) {
                $table->dropIndex(['status']);
            }
            if ($this->indexExists('orders', 'orders_customer_id_status_index')) {
                $table->dropIndex(['customer_id', 'status']);
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if ($this->indexExists('customers', 'customers_phone_index')) {
                $table->dropIndex(['phone']);
            }
            if ($this->indexExists('customers', 'customers_document_index')) {
                $table->dropIndex(['document']);
            }
            if ($this->indexExists('customers', 'customers_active_index')) {
                $table->dropIndex(['active']);
            }
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            if ($this->indexExists('inventory_movements', 'inventory_movements_product_id_type_index')) {
                $table->dropIndex(['product_id', 'type']);
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if ($this->indexExists('payments', 'payments_reference_index')) {
                $table->dropIndex(['reference']);
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]);

        return ! empty($indexes);
    }
};
