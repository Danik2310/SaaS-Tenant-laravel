<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace the plain unique index on admin_users.email with a partial
     * (functional) unique index so a soft-deleted account no longer blocks
     * reusing its email for a new staff member.
     */
    public function up(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropUnique('admin_users_email_unique');
        });

        DB::statement('ALTER TABLE admin_users ADD UNIQUE INDEX admin_users_email_unique ((CASE WHEN deleted_at IS NULL THEN email END))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE admin_users DROP INDEX admin_users_email_unique');

        Schema::table('admin_users', function (Blueprint $table) {
            $table->unique('email');
        });
    }
};
