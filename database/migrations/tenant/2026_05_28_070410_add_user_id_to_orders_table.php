<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'user_id')) {
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->index('user_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('orders', 'user_id')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if ($this->foreignKeyExists('orders', 'orders_user_id_foreign')) {
                $table->dropForeign(['user_id']);
            }
            if ($this->indexExists('orders', 'orders_user_id_index')) {
                $table->dropIndex(['user_id']);
            }
            $table->dropColumn('user_id');
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]);

        return ! empty($indexes);
    }

    private function foreignKeyExists(string $table, string $foreignKeyName): bool
    {
        $constraints = DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$table, $foreignKeyName]
        );

        return ! empty($constraints);
    }
};
