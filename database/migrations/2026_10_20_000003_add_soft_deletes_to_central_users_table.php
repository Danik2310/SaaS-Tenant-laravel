<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add `deleted_at` to the central `users` table so it stays aligned with the
 * tenant copy (2026_10_20_000001_add_soft_deletes_to_users_table).
 *
 * `App\Models\User` now uses SoftDeletes and can be resolved in central
 * context (it is registered with UserObserver and referenced by tenant-agnostic
 * requests), so the central schema must carry the same column — exactly as
 * 2026_10_16_000003 mirrored `is_active` for the same reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'deleted_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'deleted_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
