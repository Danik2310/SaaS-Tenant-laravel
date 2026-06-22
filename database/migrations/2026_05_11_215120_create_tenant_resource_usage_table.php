<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_resource_usage')) {
            Schema::create('tenant_resource_usage', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->integer('users_count')->default(0);
                $table->bigInteger('storage_kb')->default(0);
                $table->bigInteger('db_size_kb')->default(0);
                $table->integer('products_count')->default(0);
                $table->integer('orders_count')->default(0);
                $table->timestamp('collected_at')->nullable();
                $table->timestamps();

                $table->unique('tenant_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_resource_usage');
    }
};
