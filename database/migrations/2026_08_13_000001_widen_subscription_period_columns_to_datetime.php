<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen subscription period columns from DATE to DATETIME so plan and
     * trial periods keep exact timestamps (times were being truncated to
     * midnight, losing the precise usage period of each tenant).
     */
    public function up(): void
    {
        if (! Schema::hasTable('subscriptions')) {
            return;
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::getColumnType('subscriptions', 'starts_at') === 'date') {
                $table->dateTime('starts_at')->change();
            }

            if (Schema::getColumnType('subscriptions', 'ends_at') === 'date') {
                $table->dateTime('ends_at')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('subscriptions')) {
            return;
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::getColumnType('subscriptions', 'starts_at') === 'datetime') {
                $table->date('starts_at')->change();
            }

            if (Schema::getColumnType('subscriptions', 'ends_at') === 'datetime') {
                $table->date('ends_at')->nullable()->change();
            }
        });
    }
};
