<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Billing-only fields kept separate from the personal user profile and from
     * the owner's login email. The Stripe customer id lets the portal and the
     * webhook resolve a tenant without storing any card details locally.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'billing_email')) {
                $table->string('billing_email', 255)->nullable()->after('email');
            }

            if (! Schema::hasColumn('tenants', 'tax_id')) {
                $table->string('tax_id', 100)->nullable()->after('company_name');
            }

            if (! Schema::hasColumn('tenants', 'stripe_customer_id')) {
                $table->string('stripe_customer_id', 255)->nullable()->after('tax_id');
                $table->index('stripe_customer_id', 'tenants_stripe_customer_id_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasIndex('tenants', 'tenants_stripe_customer_id_index')) {
                $table->dropIndex('tenants_stripe_customer_id_index');
            }

            $columns = collect(['billing_email', 'tax_id', 'stripe_customer_id'])
                ->filter(fn (string $column) => Schema::hasColumn('tenants', $column))
                ->values()
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
