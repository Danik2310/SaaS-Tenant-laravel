<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enforce tenant email uniqueness at the database level.
 *
 * Until now `tenants.email` only had a plain (non-unique) index; uniqueness was
 * checked in the registration FormRequests with a `withTrashed()->exists()`
 * read-then-write, which is racy — two concurrent signups with the same email
 * both pass the check and both insert.
 *
 * A partial (functional) unique index is used instead of a plain unique so a
 * soft-deleted tenant does not permanently reserve its email for a rebrand —
 * the same approach 2026_09_19_000001 applied to admin_users.
 *
 * The pre-flight guard throws before touching anything if real duplicates exist,
 * so the collision is fixed deliberately rather than surfacing as an opaque
 * MySQL duplicate-key failure mid-migration.
 */
return new class extends Migration
{
    private const TABLE = 'tenants';

    private const PLAIN_INDEX = 'tenants_email_index';

    private const PARTIAL_INDEX = 'tenants_email_unique';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! Schema::hasColumn(self::TABLE, 'email')) {
            return;
        }

        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $this->assertNoLiveDuplicates();

        if (Schema::hasIndex(self::TABLE, self::PLAIN_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropIndex(self::PLAIN_INDEX);
            });
        }

        if (! Schema::hasIndex(self::TABLE, self::PARTIAL_INDEX)) {
            DB::statement(
                'ALTER TABLE tenants ADD UNIQUE INDEX tenants_email_unique '.
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

        if (Schema::hasIndex(self::TABLE, self::PARTIAL_INDEX)) {
            DB::statement('ALTER TABLE tenants DROP INDEX tenants_email_unique');
        }

        if (! Schema::hasIndex(self::TABLE, self::PLAIN_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index('email');
            });
        }
    }

    /**
     * Refuse to migrate while two live tenants share an email.
     *
     * @throws RuntimeException when duplicates are present
     */
    private function assertNoLiveDuplicates(): void
    {
        $duplicates = DB::table(self::TABLE)
            ->whereNull('deleted_at')
            ->whereNotNull('email')
            ->pluck('email')
            ->groupBy(fn (string $email): string => strtolower($email))
            ->filter(fn ($emails) => $emails->count() > 1)
            ->keys();

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot add unique email constraint: duplicate live tenant email(s): '.
                $duplicates->implode(', ').
                '. Resolve these rows first, then re-run the migration.'
            );
        }
    }
};
