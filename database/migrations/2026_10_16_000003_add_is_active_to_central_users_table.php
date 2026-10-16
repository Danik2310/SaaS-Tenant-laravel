<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aligns the central `users` table with its tenant counterpart.
 *
 * The `App\Models\User` model (web guard) lives in `app/Models` and is used by
 * tenant flows (registration, staff management) as well as being registered
 * centrally (observers in EventServiceProvider). The tenant migration
 * `database/migrations/tenant/2026_08_08_000001_add_is_active_to_users_table`
 * adds `is_active`, but the central migration did not — leaving the two copies
 * out of sync and the central table without a column the model casts/fills.
 *
 * This migration brings the central copy in line. The central `users` table is
 * intentional (it backs `App\Models\User` when the app runs in the central
 * context); it is not dropped here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('password');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
    }
};
