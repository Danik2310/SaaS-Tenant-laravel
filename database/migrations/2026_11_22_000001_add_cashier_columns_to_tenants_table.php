<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expand the central tenants row with Cashier's billable columns.
     *
     * This is deliberately expand-only: `stripe_customer_id` stays authoritative
     * for the current app code during Phase 1. The model layer mirrors every
     * write into Cashier's `stripe_id` column, so the contract migration can
     * drop the legacy column in a later deploy.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'stripe_id')) {
                $table->string('stripe_id', 255)
                    ->nullable()
                    ->collation('utf8mb4_bin')
                    ->after('stripe_customer_id');
                $table->index('stripe_id', 'tenants_stripe_id_index');
            }

            if (! Schema::hasColumn('tenants', 'pm_type')) {
                $table->string('pm_type', 255)->nullable()->after('stripe_id');
            }

            if (! Schema::hasColumn('tenants', 'pm_last_four')) {
                $table->string('pm_last_four', 4)->nullable()->after('pm_type');
            }

            if (! Schema::hasColumn('tenants', 'registration_trial_ends_at')) {
                $table->timestamp('registration_trial_ends_at')->nullable()->after('trial_ends_at');
            }
        });

        DB::table('tenants')
            ->whereNotNull('stripe_customer_id')
            ->whereNull('stripe_id')
            ->update(['stripe_id' => DB::raw('stripe_customer_id')]);

        DB::table('tenants')
            ->whereNotNull('trial_ends_at')
            ->whereNull('registration_trial_ends_at')
            ->update(['registration_trial_ends_at' => DB::raw('trial_ends_at')]);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasIndex('tenants', 'tenants_stripe_id_index')) {
                $table->dropIndex('tenants_stripe_id_index');
            }

            $columns = collect(['stripe_id', 'pm_type', 'pm_last_four', 'registration_trial_ends_at'])
                ->filter(fn (string $column) => Schema::hasColumn('tenants', $column))
                ->values()
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
