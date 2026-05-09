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
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'status']);
        });
    }
};
