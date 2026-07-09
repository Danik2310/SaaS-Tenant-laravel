<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_movements')) {
            return;
        }

        DB::statement("ALTER TABLE inventory_movements MODIFY COLUMN type VARCHAR(255) NOT NULL DEFAULT 'in'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventory_movements')) {
            return;
        }

        $invalid = DB::table('inventory_movements')
            ->whereNotIn('type', ['in', 'out', 'adjustment'])
            ->exists();

        if ($invalid) {
            throw new RuntimeException('Cannot revert: inventory_movements have type values outside [in, out, adjustment]');
        }

        DB::statement("ALTER TABLE inventory_movements MODIFY COLUMN type ENUM('in', 'out', 'adjustment') NOT NULL DEFAULT 'in'");
    }
};
