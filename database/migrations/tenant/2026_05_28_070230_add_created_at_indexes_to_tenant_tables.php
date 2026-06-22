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
        Schema::table('orders', function (Blueprint $table) {
            if (! $this->indexExists('orders', 'orders_created_at_index')) {
                $table->index('created_at');
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if (! $this->indexExists('customers', 'customers_created_at_index')) {
                $table->index('created_at');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (! $this->indexExists('products', 'products_created_at_index')) {
                $table->index('created_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if ($this->indexExists('orders', 'orders_created_at_index')) {
                $table->dropIndex(['created_at']);
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if ($this->indexExists('customers', 'customers_created_at_index')) {
                $table->dropIndex(['created_at']);
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if ($this->indexExists('products', 'products_created_at_index')) {
                $table->dropIndex(['created_at']);
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]);

        return ! empty($indexes);
    }
};
