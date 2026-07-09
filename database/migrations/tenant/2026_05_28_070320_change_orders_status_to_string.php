<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        DB::statement("ALTER TABLE orders MODIFY COLUMN status VARCHAR(255) DEFAULT 'pending' NOT NULL");
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        $invalid = DB::table('orders')
            ->whereNotIn('status', ['pending', 'paid', 'cancelled'])
            ->exists();

        if ($invalid) {
            throw new RuntimeException('Cannot revert: orders have status values outside [pending, paid, cancelled]');
        }

        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending' NOT NULL");
    }
};
