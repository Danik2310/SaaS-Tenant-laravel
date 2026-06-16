<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'name')) {
                $table->string('name')->nullable()->after('id');
            }
            if (! Schema::hasColumn('tenants', 'email')) {
                $table->string('email')->nullable()->after('name');
                $table->index('email');
            }
            // Only add status if it doesn't exist (migration may have been updated)
            if (! Schema::hasColumn('tenants', 'status') && ! Schema::hasColumn('tenants', 'is_active')) {
                $table->enum('status', ['Active', 'Suspended'])->default('Active')->after('email');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $columns = [];
            foreach (['name', 'email', 'status'] as $col) {
                if (Schema::hasColumn('tenants', $col)) {
                    $columns[] = $col;
                }
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
