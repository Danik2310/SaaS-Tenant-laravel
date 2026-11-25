<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a masked, admin-facing card projection to the central tenant row.
     *
     * Stripe remains the source of truth. These columns are only a display
     * cache so the tenant registry never needs a Stripe call per row. No PAN or
     * CVC is stored — only the same masked metadata Cashier already uses.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'pm_exp_month')) {
                $table->unsignedTinyInteger('pm_exp_month')->nullable()->after('pm_last_four');
            }

            if (! Schema::hasColumn('tenants', 'pm_exp_year')) {
                $table->unsignedSmallInteger('pm_exp_year')->nullable()->after('pm_exp_month');
            }

            if (! Schema::hasColumn('tenants', 'pm_card_count')) {
                $table->unsignedInteger('pm_card_count')->default(0)->after('pm_exp_year');
            }

            if (! Schema::hasColumn('tenants', 'pm_synced_at')) {
                $table->timestamp('pm_synced_at')->nullable()->after('pm_card_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $columns = collect(['pm_exp_month', 'pm_exp_year', 'pm_card_count', 'pm_synced_at'])
                ->filter(fn (string $column) => Schema::hasColumn('tenants', $column))
                ->values()
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
