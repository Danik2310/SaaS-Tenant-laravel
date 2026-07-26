<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (! Schema::hasColumn('plans', 'status')) {
                $table->string('status')->default('active')->after('slug');
            }
            if (! Schema::hasColumn('plans', 'duration_months')) {
                $table->unsignedInteger('duration_months')->nullable()->after('price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('plans', 'status')) {
                $columns[] = 'status';
            }
            if (Schema::hasColumn('plans', 'duration_months')) {
                $columns[] = 'duration_months';
            }
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
