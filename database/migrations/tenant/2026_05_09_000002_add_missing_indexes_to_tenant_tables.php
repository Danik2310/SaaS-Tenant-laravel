<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
            $table->index('status');
            $table->index(['customer_id', 'status']);
        });

        // Customers
        Schema::table('customers', function (Blueprint $table) {
            $table->index('phone');
            $table->index('document');
            $table->index('active');
        });

        // Inventory movements
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->index(['product_id', 'type']);
        });

        // Payments
        Schema::table('payments', function (Blueprint $table) {
            $table->index('reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['customer_id', 'status']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['phone']);
            $table->dropIndex(['document']);
            $table->dropIndex(['active']);
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'type']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['reference']);
        });
    }
};
