<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pins the canonical definition of `payment_methods.provider`.
 *
 * History left three conflicting definitions in the migration ledger:
 *   2026_03_21_171743_create_payment_methods_table   → ENUM('stripe','paypal','other') NOT NULL (no default)
 *   2026_05_28_070500_change_payment_methods...      → VARCHAR(255) NOT NULL DEFAULT 'other'
 *   2026_05_29_000004_optimize_column_types...       → VARCHAR(30) DEFAULT NULL
 *
 * The final — and canonical — state is a nullable VARCHAR(30) (a payment method
 * may exist before a provider is chosen, and values are validated at the
 * application layer). This migration re-asserts that state explicitly so the
 * intent is documented and cannot silently drift.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('payment_methods') && Schema::hasColumn('payment_methods', 'provider')) {
            DB::statement('ALTER TABLE payment_methods MODIFY COLUMN provider VARCHAR(30) DEFAULT NULL');
        }
    }

    public function down(): void
    {
        // Intentionally non-reversible: this migration only re-asserts the
        // canonical column definition. Reverting would reintroduce the
        // contradictory state this migration exists to remove.
    }
};
