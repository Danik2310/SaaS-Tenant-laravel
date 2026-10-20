<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replace the plain unique index on tenant `users.email` with a partial
 * (functional) unique index so a soft-deleted user no longer permanently
 * reserves its email for a new team member.
 *
 * The `users` table historically had `email` as a plain unique column
 * (users_email_unique) and no `deleted_at`; soft deletes and this migration
 * arrive together (see 2026_10_20_000001_add_soft_deletes_to_users_table).
 *
 * Before swapping the index this migration repairs existing data:
 *   1. Normalizes every stored email to lowercase, so a unique index can never
 *      disagree with the FormRequest's `lowercase` rule.
 *   2. Releases duplicate emails among *live* rows — a live duplicate would make
 *      the new index fail, and the plain index could only have allowed it if it
 *      was ever dropped/recreated. Oldest row keeps the address; later rows have
 *      it nulled so the account is retained but no longer blocks the address.
 *
 * The same pattern was already applied to admin_users
 * (2026_09_19_000001) and tenants (2026_10_17_000003).
 */
return new class extends Migration
{
    private const TABLE = 'users';

    private const PLAIN_INDEX = 'users_email_unique';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! Schema::hasColumn(self::TABLE, 'email')) {
            return;
        }

        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        // Deduplication (below) and a future rollback both NULL released
        // addresses, so the column must accept NULL first. Mirrors
        // 2026_10_17_000005 for admin_users.
        if (! $this->emailIsNullable()) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->string('email')->nullable()->change();
            });
        }

        $this->normalizeEmails();
        $this->releaseLiveDuplicateEmails();

        if (Schema::hasIndex(self::TABLE, self::PLAIN_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropUnique(self::PLAIN_INDEX);
            });
        }

        if (! Schema::hasIndex(self::TABLE, self::PLAIN_INDEX)) {
            DB::statement(
                'ALTER TABLE users ADD UNIQUE INDEX users_email_unique '.
                '((CASE WHEN deleted_at IS NULL THEN email END))'
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        // The partial index permitted soft-deleted rows to share an email; a
        // plain unique index does not. Release the email on the soft-deleted
        // duplicates first so the restore cannot fail with a duplicate-key
        // error.
        $this->releaseSoftDeletedDuplicateEmails();

        if (Schema::hasIndex(self::TABLE, self::PLAIN_INDEX)) {
            DB::statement('ALTER TABLE users DROP INDEX users_email_unique');
        }

        if (! Schema::hasIndex(self::TABLE, self::PLAIN_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->unique('email');
            });
        }

        // Not-NULL cannot be restored while released addresses are still NULL.
        // Fail loudly rather than silently truncating data to ''.
        if ($this->emailIsNullable()) {
            $released = DB::table(self::TABLE)->whereNull('email')->count();

            if ($released > 0) {
                throw new RuntimeException(
                    "Cannot restore NOT NULL on users.email: {$released} row(s) hold a NULL email "
                    .'(released by a rollback). Restore an address on those rows first, then re-run.'
                );
            }

            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->string('email')->nullable(false)->change();
            });
        }
    }

    private function emailIsNullable(): bool
    {
        $column = collect(Schema::getColumns(self::TABLE))->firstWhere('name', 'email');

        return (bool) ($column['nullable'] ?? false);
    }

    /**
     * Lowercase every stored email so the unique index and the FormRequest
     * agree on a canonical form.
     */
    private function normalizeEmails(): void
    {
        DB::table(self::TABLE)
            ->whereNotNull('email')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $normalized = strtolower($row->email);

                    if ($normalized !== $row->email) {
                        DB::table(self::TABLE)
                            ->where('id', $row->id)
                            ->update(['email' => $normalized]);
                    }
                }
            });
    }

    /**
     * Null the email on live rows that share it with another live row. Oldest
     * (lowest id) row keeps the address; later rows are released so the new
     * unique index can be created.
     */
    private function releaseLiveDuplicateEmails(): void
    {
        $duplicates = DB::table(self::TABLE)
            ->select('email')
            ->whereNull('deleted_at')
            ->whereNotNull('email')
            ->groupBy('email')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('email');

        foreach ($duplicates as $email) {
            $keeperId = DB::table(self::TABLE)
                ->where('email', $email)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->value('id');

            DB::table(self::TABLE)
                ->where('email', $email)
                ->whereNull('deleted_at')
                ->where('id', '!=', $keeperId)
                ->update(['email' => null]);
        }
    }

    /**
     * Null the email on soft-deleted rows that share it with another row, so a
     * plain unique index can be restored during rollback. At most one live row
     * per email can exist under the partial index, so live accounts are never
     * touched.
     */
    private function releaseSoftDeletedDuplicateEmails(): void
    {
        $duplicates = DB::table(self::TABLE)
            ->select('email')
            ->whereNotNull('email')
            ->groupBy('email')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('email');

        foreach ($duplicates as $email) {
            $keeperId = DB::table(self::TABLE)
                ->where('email', $email)
                ->orderByRaw('deleted_at IS NULL DESC')
                ->orderBy('id')
                ->value('id');

            DB::table(self::TABLE)
                ->where('email', $email)
                ->where('id', '!=', $keeperId)
                ->update(['email' => null]);
        }
    }
};
