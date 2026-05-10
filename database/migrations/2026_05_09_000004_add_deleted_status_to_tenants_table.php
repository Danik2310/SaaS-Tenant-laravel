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
        DB::statement("ALTER TABLE tenants MODIFY COLUMN status ENUM('Active', 'Suspended') NOT NULL DEFAULT 'Active'");
    }
};
