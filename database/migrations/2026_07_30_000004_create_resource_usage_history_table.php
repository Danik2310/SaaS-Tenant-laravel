<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('resource_usage_history')) {
            Schema::create('resource_usage_history', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->date('snapshot_date');
                $table->integer('users_count')->default(0);
                $table->bigInteger('storage_kb')->default(0);
                $table->bigInteger('db_size_kb')->default(0);
                $table->integer('products_count')->default(0);
                $table->integer('orders_count')->default(0);

                $table->unique(['tenant_id', 'snapshot_date']);
                $table->index('snapshot_date');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_usage_history');
    }
};
