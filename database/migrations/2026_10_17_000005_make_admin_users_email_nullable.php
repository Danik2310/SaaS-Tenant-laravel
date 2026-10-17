<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Allow `admin_users.email` to be NULLed when a rollback releases a duplicate.
 *
 * `2026_09_19_000001::releaseDuplicateSoftDeletedEmails()` nulls the address of
 * a soft-deleted duplicate so a plain unique index can be restored in `down()`.
 * The column was created NOT NULL (`2026_03_05_create_admin_users_table`), so
 * that UPDATE failed with 1048 "Column 'email' cannot be null" and the rollback
 * aborted, stranding the partial index. Only soft-deleted duplicates are ever
 * NULLed — live accounts keep an address, and every form request still requires
 * one on create.
 *
 * `tenants.email` has been nullable since `2026_03_02_190643`, so this also
 * brings the two email columns in line.
 */
return new class extends Migration
{
    private const TABLE = 'admin_users';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! Schema::hasColumn(self::TABLE, 'email')) {
            return;
        }

        if ($this->emailIsNullable()) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! Schema::hasColumn(self::TABLE, 'email')) {
            return;
        }

        if (! $this->emailIsNullable()) {
            return;
        }

        // Not NULL cannot be restored while released addresses are still NULL.
        // Fail loudly rather than silently truncating data to ''.
        $released = DB::table(self::TABLE)->whereNull('email')->count();

        if ($released > 0) {
            throw new RuntimeException(
                "Cannot restore NOT NULL on admin_users.email: {$released} row(s) hold a NULL email "
                .'(released by a rollback). Restore an address on those rows first, then re-run.'
            );
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }

    private function emailIsNullable(): bool
    {
        $column = collect(Schema::getColumns(self::TABLE))->firstWhere('name', 'email');

        return (bool) ($column['nullable'] ?? false);
    }
};
