<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the dead `domain` column from `tenants`.
 *
 * It was a denormalised copy of the tenant's primary domain that was only ever
 * written, never read for behaviour: every read goes through
 * Tenant::getDomainAttribute(), which resolves the primary (or latest) row from
 * the `domains` table. Writing it also forced a redundant second save of the
 * tenant row during provisioning.
 *
 * (`tenancy_db_name` on the same table is NOT dead — stancl's
 * DatabaseConfig::makeCredentials() writes it and DatabaseConfig::getName()
 * reads it — so it is deliberately left untouched.)
 */
return new class extends Migration
{
    private const TABLE = 'tenants';

    public function up(): void
    {
        if (Schema::hasColumn(self::TABLE, 'domain')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropColumn('domain');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn(self::TABLE, 'domain')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->string('domain')->nullable()->unique()->after('email');
            });
        }
    }
};
