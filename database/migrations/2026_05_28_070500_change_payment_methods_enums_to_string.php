<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE payment_methods MODIFY COLUMN provider VARCHAR(255) NOT NULL DEFAULT 'other'");
        DB::statement("ALTER TABLE payment_methods MODIFY COLUMN mode VARCHAR(255) NOT NULL DEFAULT 'test'");
    }

    public function down(): void
    {
        $invalidProviders = DB::table('payment_methods')
            ->whereNotIn('provider', ['stripe', 'paypal', 'other'])
            ->exists();

        $invalidModes = DB::table('payment_methods')
            ->whereNotIn('mode', ['test', 'live'])
            ->exists();

        if ($invalidProviders) {
            throw new RuntimeException(
                'Cannot revert — some payment methods have provider values outside [stripe, paypal, other]. '
                .'Update those records to a valid enum value first.'
            );
        }

        if ($invalidModes) {
            throw new RuntimeException(
                'Cannot revert — some payment methods have mode values outside [test, live]. '
                .'Update those records to a valid enum value first.'
            );
        }

        DB::statement("ALTER TABLE payment_methods MODIFY COLUMN mode ENUM('test', 'live') NOT NULL DEFAULT 'test'");
        DB::statement("ALTER TABLE payment_methods MODIFY COLUMN provider ENUM('stripe', 'paypal', 'other') NOT NULL DEFAULT 'other'");
    }
};
