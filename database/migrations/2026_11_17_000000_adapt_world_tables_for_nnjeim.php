<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adopt the nnjeim/world schema on databases created by altwaireb/laravel-world.
 *
 * The package migrations run before this one and skip tables that already exist,
 * so existing installations keep the old columns. Fresh installations that ran
 * the package migrations already have the new schema and are left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->usesLegacySchema()) {
            return;
        }

        if (Schema::hasColumn('countries', 'phonecode') && ! Schema::hasColumn('countries', 'phone_code')) {
            Schema::table('countries', fn (Blueprint $table) => $table->renameColumn('phonecode', 'phone_code'));
        }

        if (! Schema::hasColumn('countries', 'status')) {
            Schema::table('countries', function (Blueprint $table) {
                $table->tinyInteger('status')->default(1);
            });

            DB::table('countries')->update(['status' => DB::raw('is_active')]);
        }

        foreach (['states', 'cities'] as $tableName) {
            if (! Schema::hasColumn($tableName, 'country_code')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->string('country_code', 3)->nullable();
                });

                DB::table($tableName)->update([
                    'country_code' => DB::raw('(select iso2 from countries where countries.id = '.$tableName.'.country_id)'),
                ]);
            }
        }

        foreach (['countries', 'states', 'cities'] as $tableName) {
            $drop = array_filter(
                ['is_active', 'deleted_at'],
                fn (string $column) => Schema::hasColumn($tableName, $column),
            );

            if ($drop !== []) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn(array_values($drop)));
            }
        }
    }

    public function down(): void
    {
        if (! $this->usesLegacySchema()) {
            return;
        }

        foreach (['countries', 'states', 'cities'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->boolean('is_active')->index()->default(true);
                $table->softDeletes();
            });
        }

        if (Schema::hasColumn('countries', 'status')) {
            DB::table('countries')->update(['is_active' => DB::raw('status')]);

            Schema::table('countries', fn (Blueprint $table) => $table->dropColumn('status'));
        }

        if (Schema::hasColumn('countries', 'phone_code') && ! Schema::hasColumn('countries', 'phonecode')) {
            Schema::table('countries', fn (Blueprint $table) => $table->renameColumn('phone_code', 'phonecode'));
        }

        foreach (['states', 'cities'] as $tableName) {
            if (Schema::hasColumn($tableName, 'country_code')) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn('country_code'));
            }
        }
    }

    /**
     * The legacy tables carry a `flag` column that nnjeim/world never creates.
     */
    private function usesLegacySchema(): bool
    {
        return Schema::hasTable('countries') && Schema::hasColumn('countries', 'flag');
    }
};
