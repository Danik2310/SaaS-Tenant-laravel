<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the single-column `tenant_id` index on subscription_payments.
 *
 * 2026_10_10_163204 added the compound (tenant_id, paid_at) index. InnoDB uses
 * leftmost prefixes, so any lookup that could use `subscription_payments_-
 * tenant_id_index` already uses the compound index instead — the single-column
 * index is now pure write overhead (every INSERT maintains both).
 *
 * Guarded so it only drops when the compound index that replaces it exists.
 */
return new class extends Migration
{
    private const TABLE = 'subscription_payments';

    private const REDUNDANT = 'subscription_payments_tenant_id_index';

    private const REPLACEMENT = 'subscription_payments_tenant_id_paid_at_index';

    public function up(): void
    {
        if (
            Schema::hasTable(self::TABLE)
            && Schema::hasIndex(self::TABLE, self::REPLACEMENT)
            && Schema::hasIndex(self::TABLE, self::REDUNDANT)
        ) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropIndex(self::REDUNDANT);
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable(self::TABLE)
            && ! Schema::hasIndex(self::TABLE, self::REDUNDANT)
        ) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index('tenant_id');
            });
        }
    }
};
