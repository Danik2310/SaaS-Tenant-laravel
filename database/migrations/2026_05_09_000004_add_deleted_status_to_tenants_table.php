<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE tenants MODIFY COLUMN status ENUM('Active', 'Suspended', 'Deleted') NOT NULL DEFAULT 'Active'");
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
