<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function ($table) {
            if (Schema::hasColumn('tenants', 'data_placeholder')) {
                $table->dropColumn('data_placeholder');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function ($table) {
            if (! Schema::hasColumn('tenants', 'data_placeholder')) {
                $table->json('data_placeholder')->nullable()->after('tenancy_db_name');
            }
        });
    }
};
