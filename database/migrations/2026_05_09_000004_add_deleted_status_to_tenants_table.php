<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenants') && Schema::hasColumn('tenants', 'status')) {
            DB::statement("ALTER TABLE tenants MODIFY COLUMN status ENUM('Active', 'Suspended', 'Deleted') NOT NULL DEFAULT 'Active'");
        }
    }

    public function down(): void
    {
        $hasDeleted = DB::table('tenants')
            ->where('status', 'Deleted')
            ->exists();

        if ($hasDeleted) {
            throw new RuntimeException(
                'Cannot revert — some tenants have status "Deleted". '
                .'Force-restore or permanently delete those records first.'
            );
        }

        DB::statement("ALTER TABLE tenants MODIFY COLUMN status ENUM('Active', 'Suspended') NOT NULL DEFAULT 'Active'");
    }
};
