<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Map a Stripe recurring price to the local plan it entitles.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (! Schema::hasColumn('plans', 'stripe_price_id')) {
                $table->string('stripe_price_id', 255)
                    ->nullable()
                    ->collation('utf8mb4_bin')
                    ->after('slug');
            }

            if (! Schema::hasIndex('plans', 'plans_stripe_price_id_unique')) {
                $table->unique('stripe_price_id', 'plans_stripe_price_id_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (Schema::hasIndex('plans', 'plans_stripe_price_id_unique')) {
                $table->dropUnique('plans_stripe_price_id_unique');
            }

            if (Schema::hasColumn('plans', 'stripe_price_id')) {
                $table->dropColumn('stripe_price_id');
            }
        });
    }
};
