<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds composite index on [tenant_id, status] for the most common
     * subscription query pattern: "find active/pending/cancelled subscriptions
     * for a given tenant".
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * MySQL InnoDB uses the composite index [tenant_id, status] to enforce
     * the foreign key constraint on tenant_id. We must drop the FK first,
     * then the index, then re-add the FK (which auto-creates a new
     * single-column index for the constraint).
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id', 'status']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
        });
    }
};
