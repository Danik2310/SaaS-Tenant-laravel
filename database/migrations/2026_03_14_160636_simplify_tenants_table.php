<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, add the domain column
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('domain')->nullable()->unique()->after('email');
            $table->string('tenancy_db_name')->nullable()->after('domain');
            $table->json('data_placeholder')->nullable()->after('tenancy_db_name');
        });

        // Migrate primary domains to the tenants table
        $primaryDomains = DB::table('domains')
            ->where('is_primary', true)
            ->get();

        foreach ($primaryDomains as $domain) {
            DB::table('tenants')
                ->where('id', $domain->tenant_id)
                ->update(['domain' => $domain->domain]);
        }

        // Backfill existing data into data_placeholder before dropping
        DB::table('tenants')->whereNotNull('data')->orderBy('id')->each(function ($tenant) {
            DB::table('tenants')
                ->where('id', $tenant->id)
                ->update(['data_placeholder' => $tenant->data]);
        });

        // Remove the data column
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back the data column (only if it doesn't already exist)
        if (! Schema::hasColumn('tenants', 'data')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->json('data')->nullable();
            });
        }

        // Remove the added columns (check if they exist first)
        Schema::table('tenants', function (Blueprint $table) {
            $columns = Schema::getColumnListing('tenants');
            if (in_array('domain', $columns)) {
                $table->dropColumn('domain');
            }
            if (in_array('tenancy_db_name', $columns)) {
                $table->dropColumn('tenancy_db_name');
            }
            if (in_array('data_placeholder', $columns)) {
                $table->dropColumn('data_placeholder');
            }
        });

        // Remove the data column if it was recreated and now duplicates
        if (Schema::hasColumn('tenants', 'data')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('data');
            });
        }
    }
};
