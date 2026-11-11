<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Project the tenant owner's email confirmation onto the central tenant row.
 *
 * `users.email_verified_at` in the tenant database remains the source of truth.
 * This column is written by the Verified listener so central readers — the
 * pending registration screen, the landlord dashboard and the broadcast — can
 * answer "is the owner confirmed?" without initialising tenancy.
 */
return new class extends Migration
{
    private const TABLE = 'tenants';

    private const COLUMN = 'owner_email_verified_at';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE) || Schema::hasColumn(self::TABLE, self::COLUMN)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->timestamp(self::COLUMN)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn(self::TABLE, self::COLUMN)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropColumn(self::COLUMN);
        });
    }
};
