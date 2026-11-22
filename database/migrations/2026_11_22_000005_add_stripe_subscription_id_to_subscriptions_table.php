<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tie each local projection row to the Cashier/Stripe subscription it mirrors.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'stripe_subscription_id')) {
                $table->string('stripe_subscription_id', 255)
                    ->nullable()
                    ->collation('utf8mb4_bin')
                    ->after('plan_id');
            }

            if (! Schema::hasIndex('subscriptions', 'subscriptions_stripe_subscription_id_unique')) {
                $table->unique('stripe_subscription_id', 'subscriptions_stripe_subscription_id_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasIndex('subscriptions', 'subscriptions_stripe_subscription_id_unique')) {
                $table->dropUnique('subscriptions_stripe_subscription_id_unique');
            }

            if (Schema::hasColumn('subscriptions', 'stripe_subscription_id')) {
                $table->dropColumn('stripe_subscription_id');
            }
        });
    }
};
