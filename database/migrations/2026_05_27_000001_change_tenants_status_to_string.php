<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'status')) {
                DB::statement('ALTER TABLE tenants MODIFY COLUMN status VARCHAR(255) DEFAULT \'Active\'');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'status')) {
                $invalid = DB::table('tenants')
                    ->whereNotIn('status', ['Active', 'Suspended'])
                    ->exists();

                if ($invalid) {
                    throw new RuntimeException('Cannot revert: tenants have status values outside [Active, Suspended]');
                }

                DB::statement('ALTER TABLE tenants MODIFY COLUMN status ENUM(\'Active\', \'Suspended\') DEFAULT \'Active\'');
            }
        });
    }
};
