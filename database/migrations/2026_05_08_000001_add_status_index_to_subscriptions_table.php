<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('subscriptions', 'subscriptions_status_index')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('subscriptions', 'subscriptions_status_index')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropIndex('subscriptions_status_index');
            });
        }
    }
};
