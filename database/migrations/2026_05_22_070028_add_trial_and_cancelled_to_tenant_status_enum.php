<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE tenants MODIFY COLUMN status ENUM('Active', 'Trial', 'Suspended', 'Cancelled', 'Deleted') NOT NULL DEFAULT 'Active'");
    }

    public function down(): void
    {
        $hasTrialOrCancelled = DB::table('tenants')
            ->whereIn('status', ['Trial', 'Cancelled'])
            ->exists();

        if ($hasTrialOrCancelled) {
            throw new RuntimeException(
                'Cannot revert — some tenants have status "Trial" or "Cancelled". '
                .'Change those records to a different status first.'
            );
        }

        DB::statement("ALTER TABLE tenants MODIFY COLUMN status ENUM('Active', 'Suspended', 'Deleted') NOT NULL DEFAULT 'Active'");
    }
};
